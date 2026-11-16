<?php

namespace App\Shared\Game;

use App\Entity\Game;
use App\Entity\GameMatch;
use App\Entity\User;
use App\Repository\GameMatchRepository;
use App\Sdk\Trait\ClockTrait;
use App\Shared\Engage\RatingManager;
use App\Shared\Game\Enum\GameMatchModeEnum;
use App\Shared\Game\Enum\GameMatchStatusEnum;
use App\Shared\Game\Exception\GameActionException;
use NeoPHP\Package\Mercure\MercureManagerInterface;
use NeoPHP\Package\Mercure\Model\Update;

class RankedQueue
{
    use ClockTrait;

    public const INITIAL_GAP = 100;
    public const GAP_STEP = 50;
    public const STEP_MS = 15000;
    public const MAX_GAP = 600;

    private const PRESENCE_MS = 45000;
    private const FILL_MS = 15000;

    protected array $changed = [];

    public function __construct(
        protected GameMatchRepository $repository,
        protected GameMatchManager $matches,
        protected RatingManager $ratings,
        protected MercureManagerInterface $mercure,
    ) {
    }

    public static function gap(int $queuedAt, int $now): int
    {
        return min(
            self::MAX_GAP,
            self::INITIAL_GAP
            + intdiv(
                max(0, $now - $queuedAt),
                self::STEP_MS,
            ) * self::GAP_STEP,
        );
    }

    public function enter(Game $game, User $user): GameMatch
    {
        if ($user->isGuest() || $game->getMaxPlayers() < 2) {
            throw new GameActionException();
        }

        return $this->locked($game, function () use ($game, $user): GameMatch {
            $waiting = $this->waiting($game);
            $current = null;

            foreach ($waiting as $match) {
                if ($match->hasPlayer($user)) {
                    $current = $match;
                    break;
                }
            }

            if ($current === null) {
                do {
                    $code = strtoupper(bin2hex(random_bytes(3)));
                } while (
                    $this->repository->findOneBy(['code' => $code]) !== null
                );

                $current = (new GameMatch())
                    ->setGame($game)
                    ->setMode(GameMatchModeEnum::RANKED)
                    ->setCode($code)
                    ->addPlayer($user)
                    ->mark('createdAt', $this->now());

                $players = $current->getPlayers();
                $players[0]['queueRating'] = $this->ratings
                    ->rating($user, $game)
                    ->getRating();

                $current->setPlayers($players);
                $this->persist($current);
                $waiting[] = $current;
            }

            $this->touch($current, $user);

            return $this->combine($current, $waiting);
        });
    }

    public function poll(GameMatch $match, User $user): GameMatch
    {
        return $this->locked(
            $match->getGame(),
            function () use ($match, $user): GameMatch {
                $current = $this->current($match, $user);

                if ($current->getStatus() !== GameMatchStatusEnum::WAITING) {
                    return $current;
                }

                $this->touch($current, $user);

                return $this->combine(
                    $current,
                    $this->waiting($current->getGame()),
                );
            },
        );
    }

    public function cancel(GameMatch $match, User $user): GameMatch
    {
        return $this->locked(
            $match->getGame(),
            function () use ($match, $user): GameMatch {
                $current = $this->current($match, $user);

                if ($current->getStatus() !== GameMatchStatusEnum::WAITING) {
                    return $current;
                }

                $current->removePlayer($user);

                if ($current->getPlayers() === []) {
                    $current->setStatus(GameMatchStatusEnum::CANCELLED);
                }

                if (count($current->getPlayers()) < $this->minimum($current)) {
                    $current->unmark('fillEndsAt');
                }

                $this->persist($current);

                return $current;
            },
        );
    }

    protected function locked(Game $game, callable $callback): GameMatch
    {
        $connection = $this->repository->getOrm()->getConnection();
        $this->changed = [];

        $result = $connection->transactional(
            function () use ($connection, $game, $callback): GameMatch {
                $connection->fetchOne(
                    'SELECT id FROM game WHERE id = :id FOR UPDATE',
                    ['id' => $game->getId()],
                );

                return $callback();
            },
        );

        foreach ($this->changed as $match) {
            $this->mercure->publish(Update::json(
                $this->matches->topic($match),
                $this->matches->snapshot($match),
            ));
        }

        return $result;
    }

    protected function current(GameMatch $match, User $user): GameMatch
    {
        $visited = [];

        while (!isset($visited[$match->getCode()])) {
            $visited[$match->getCode()] = true;
            $this->repository->getOrm()->refresh($match);

            if (
                $match->getMode() !== GameMatchModeEnum::RANKED
                || !$match->hasPlayer($user)
            ) {
                throw new GameActionException();
            }

            if (
                $match->getStatus() !== GameMatchStatusEnum::CANCELLED
                || $match->getNextCode() === null
            ) {
                return $match;
            }

            $match = $this->repository->findOneBy([
                'code' => $match->getNextCode(),
            ]) ?? throw new GameActionException();
        }

        throw new GameActionException();
    }

    protected function touch(GameMatch $match, User $user): void
    {
        $players = $match->getPlayers();
        $now = $this->now();

        foreach ($players as &$player) {
            if ($player['id'] !== (string) $user->getId()) {
                continue;
            }

            $lastSeen = $player['queueSeenAt']
                ?? $player['queuedAt']
                ?? $now;

            if ($now - $lastSeen > self::PRESENCE_MS) {
                $player['queuedAt'] = $now;
            }

            $player['queuedAt'] ??= $now;
            $player['queueSeenAt'] = $now;
            $player['queueRating'] ??= $this->ratings
                ->rating($user, $match->getGame())
                ->getRating();
        }

        unset($player);

        $match->setPlayers($players);
        $this->persist($match);
    }

    protected function waiting(Game $game): array
    {
        $waiting = [];
        $now = $this->now();

        $matches = $this->repository->findBy([
            'game' => $game,
            'mode' => GameMatchModeEnum::RANKED,
            'status' => GameMatchStatusEnum::WAITING,
        ], ['id' => 'ASC']);

        foreach ($matches as $match) {
            $this->repository->getOrm()->refresh($match);

            if ($match->getStatus() !== GameMatchStatusEnum::WAITING) {
                continue;
            }

            $players = [];

            foreach ($match->getPlayers() as $player) {
                $player['queuedAt'] ??= $match->getTimeline()['createdAt']
                    ?? $now;

                $player['queueSeenAt'] ??= $player['queuedAt'];

                if ($now - $player['queueSeenAt'] > self::PRESENCE_MS) {
                    continue;
                }

                if (!isset($player['queueRating'])) {
                    $rating = $this->repository
                        ->getOrm()
                        ->getConnection()
                        ->fetchOne(
                            'SELECT rating
                             FROM user_rating
                             WHERE user_id = :user AND game_id = :game',
                            [
                                'user' => (int) $player['id'],
                                'game' => $game->getId(),
                            ],
                        );

                    $player['queueRating'] = $rating === false || $rating === null
                        ? RatingManager::START
                        : (int) $rating;
                }

                $players[] = $player;
            }

            if ($players !== $match->getPlayers()) {
                $match->setPlayers($players);

                if ($players === []) {
                    $match->setStatus(GameMatchStatusEnum::CANCELLED);
                }

                if (count($players) < $this->minimum($match)) {
                    $match->unmark('fillEndsAt');
                }

                $this->persist($match);
            }

            if ($players !== []) {
                $waiting[] = $match;
            }
        }

        return $waiting;
    }

    protected function combine(GameMatch $current, array $waiting): GameMatch
    {
        while (true) {
            $best = null;
            $bestDistance = PHP_INT_MAX;

            foreach ($waiting as $candidate) {
                if (
                    $candidate->getCode() === $current->getCode()
                    || $candidate->getStatus() !== GameMatchStatusEnum::WAITING
                    || count($candidate->getPlayers())
                    + count($current->getPlayers())
                    > $current->getGame()->getMaxPlayers()
                ) {
                    continue;
                }

                $distance = $this->distance($current, $candidate);

                if ($distance !== null && $distance < $bestDistance) {
                    $best = $candidate;
                    $bestDistance = $distance;
                }
            }

            if ($best === null) {
                break;
            }

            [$target, $source] = $current->getId() < $best->getId()
                ? [$current, $best]
                : [$best, $current];

            $target->setPlayers([
                ...$target->getPlayers(),
                ...$source->getPlayers(),
            ]);

            $deadlines = array_filter([
                $target->getTimeline()['fillEndsAt'] ?? null,
                $source->getTimeline()['fillEndsAt'] ?? null,
            ]);

            if ($deadlines !== []) {
                $target->mark('fillEndsAt', min($deadlines));
            }

            $source->setStatus(GameMatchStatusEnum::CANCELLED)
                ->setNextCode($target->getCode())
                ->unmark('fillEndsAt');

            $this->persist($source);
            $this->persist($target);

            $current = $target;
        }

        if (
            count($current->getPlayers()) >= $this->minimum($current)
            && !isset($current->getTimeline()['fillEndsAt'])
        ) {
            $current->mark('fillEndsAt', $this->now() + self::FILL_MS);
        }

        $this->matches->launchRanked($current);
        $this->persist($current);

        return $current;
    }

    protected function distance(GameMatch $a, GameMatch $b): ?int
    {
        $distance = 0;
        $now = $this->now();

        foreach ($a->getPlayers() as $left) {
            foreach ($b->getPlayers() as $right) {
                if ($left['id'] === $right['id']) {
                    return null;
                }

                $gap = abs(
                    $left['queueRating'] - $right['queueRating'],
                );

                if ($gap > min(
                        self::gap($left['queuedAt'], $now),
                        self::gap($right['queuedAt'], $now),
                    )) {
                    return null;
                }

                $distance = max($distance, $gap);
            }
        }

        return $distance;
    }

    protected function minimum(GameMatch $match): int
    {
        return min(
            $match->getGame()->getMaxPlayers(),
            max(2, $match->getGame()->getMinPlayers()),
        );
    }

    protected function persist(GameMatch $match): void
    {
        $this->repository->save($match, true);
        $this->changed[$match->getCode()] = $match;
    }
}
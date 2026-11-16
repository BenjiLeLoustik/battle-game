<?php

namespace App\Shared\Game;

use App\Shared\Shop\ShopManager;
use App\Entity\Game;
use App\Entity\GameMatch;
use App\Entity\Room;
use App\Entity\User;
use App\Repository\GameMatchRepository;
use App\Sdk\Trait\ClockTrait;
use App\Shared\Game\Enum\GameMatchModeEnum;
use App\Shared\Game\Enum\GameMatchStatusEnum;
use App\Shared\Game\Exception\GameActionException;
use App\Shared\Maintenance\MaintenanceManager;
use App\Shared\Room\Enum\RoomStatusEnum;
use DateTime;
use NeoPHP\Package\Mercure\MercureManagerInterface;
use NeoPHP\Package\Mercure\Model\Update;
use Throwable;

class GameMatchManager
{
    use ClockTrait;

    private const LAUNCH_DELAY = 5000;
    private const SOLO_DELAY = 3000;
    private const FILL_DELAY = 15000;
    private const RESULTS_DELAY = 2500;
    private const VOTE_DURATION = 20000;
    private const ACTIVITY_WINDOW = 300;
    private const STALE_AFTER = 1800000;
    private const QUICK_MINIMUM = 2;

    public function __construct(
        protected GameMatchRepository $matches,
        protected GameManager $games,
        protected MercureManagerInterface $mercure,
        protected GameStatsManager $stats,
        protected MaintenanceManager $maintenance,
        protected ShopManager $shop,
    ) {
    }

    public function quickPlay(Game $game, User $user, GameMatchModeEnum $mode = GameMatchModeEnum::QUICK): GameMatch
    {
        if ($mode !== GameMatchModeEnum::QUICK) {
            throw new GameActionException();
        }

        $this->purgeStale($game);
        $waiting = $this->matches->findBy([
            'game' => $game,
            'status' => GameMatchStatusEnum::WAITING,
            'mode' => $mode,
        ], ['id' => 'ASC']);

        foreach ($waiting as $match) {
            if ($match->hasPlayer($user)) {
                return $match;
            }
        }

        foreach ($waiting as $match) {
            if (!$match->isFull()) {
                $this->join($match, $user);

                return $match;
            }
        }

        $match = (new GameMatch())
            ->setGame($game)
            ->setCode($this->code())
            ->setMode($mode)
            ->addPlayer($user)
            ->mark('createdAt', $this->now());
        $this->matches->save($match, true);

        return $match;
    }

    public function solo(Game $game, User $user, bool $preview = false): GameMatch
    {
        $this->purgeStale($game);
        $match = (new GameMatch())
            ->setGame($game)
            ->setMode(GameMatchModeEnum::SOLO)
            ->setCode($this->code())
            ->addPlayer($user)
            ->mark('createdAt', $this->now());

        if ($preview) {
            $match->mark('preview', 1);
        }

        $this->matches->save($match, true);

        return $match;
    }

    public function room(Room $room, Game $game, array $users, array $settings = []): GameMatch
    {
        $match = (new GameMatch())
            ->setGame($game)
            ->setSettings($settings)
            ->setMode(GameMatchModeEnum::ROOM)
            ->setRoom($room)
            ->setCode($this->code())
            ->mark('createdAt', $this->now());

        foreach ($users as $user) {
            $match->addPlayer($user);
        }

        $this->start($match);
        $this->save($match);

        return $match;
    }

    public function findByCode(?string $code): ?GameMatch
    {
        return $code === null ? null : $this->matches->findOneBy(['code' => $code]);
    }

    public function ready(GameMatch $match, User $user): void
    {
        if ($match->getMode() === GameMatchModeEnum::RANKED) {
            return;
        }

        if ($this->interrupt($match) || $this->isBlocked($match)) {
            return;
        }

        $fillEndsAt = $match->getTimeline()['fillEndsAt'] ?? null;
        $due = $match->isSolo() || $match->isFull() || ($fillEndsAt !== null && $this->now() >= $fillEndsAt);

        if (!$due || $match->getStatus() !== GameMatchStatusEnum::WAITING || !$match->hasPlayer($user)) {
            return;
        }

        $this->start($match, $match->isSolo() ? self::SOLO_DELAY : self::LAUNCH_DELAY);
        $this->save($match);
    }

    public function join(GameMatch $match, User $user): void
    {
        if ($match->getMode() === GameMatchModeEnum::RANKED) {
            return;
        }

        if ($match->hasPlayer($user) || $match->getStatus() !== GameMatchStatusEnum::WAITING || $match->isFull()) {
            return;
        }

        $match->addPlayer($user);

        if ($match->isFull() && !$this->isBlocked($match)) {
            $this->start($match);
        } elseif (count($match->getPlayers()) >= $this->quickMinimum($match) && !isset($match->getTimeline()['fillEndsAt'])) {
            $match->mark('fillEndsAt', $this->now() + self::FILL_DELAY);
        }

        $this->save($match);
    }

    public function leave(GameMatch $match, User $user): void
    {
        if ($match->getStatus() !== GameMatchStatusEnum::WAITING || !$match->hasPlayer($user)) {
            return;
        }

        $match->removePlayer($user);

        if (count($match->getPlayers()) < $this->quickMinimum($match)) {
            $match->unmark('fillEndsAt');
        }

        if ($match->getPlayers() === []) {
            $this->matches->delete($match, true);

            return;
        }

        $this->save($match);
    }

    public function play(GameMatch $match, User $user, string $action, array $payload): void
    {
        if ($this->interrupt($match) || !$match->hasPlayer($user) || $match->getStatus() !== GameMatchStatusEnum::PLAYING || $this->now() < $match->getTimeline()['startsAt']) {
            throw new GameActionException();
        }

        $server = $this->games->server($match->getGame());
        $connection = $this->matches->getOrm()->getConnection();
        $connection->beginTransaction();

        try {
            $locked = json_decode((string) $connection->fetchOne('SELECT state FROM game_match WHERE id = :id FOR UPDATE', ['id' => $match->getId()]), true);
            $state = $server->handle($action, $payload, (string) $user->getId(), is_array($locked) ? $locked : $match->getState());
            $match->setState($state);
            $finished = $server->isFinished($state);

            if ($finished) {
                $resultsAt = $this->now() + self::RESULTS_DELAY;
                $match->setStatus(GameMatchStatusEnum::FINISHED)
                    ->mark('finishedAt', $this->now())
                    ->mark('resultsAt', $resultsAt)
                    ->mark('voteEndsAt', $resultsAt + self::VOTE_DURATION);
            }

            if ($finished && !$match->isSolo()) {
                $this->stats->record($match, $server->results($state));
            }

            $this->matches->save($match, true);
            $connection->commit();
        } catch (Throwable $exception) {
            $connection->rollBack();

            throw $exception;
        }

        $this->mercure->publish(Update::json($this->topic($match), $this->snapshot($match)));
    }

    public function launchRanked(GameMatch $match): void
    {
        if ($match->getMode() !== GameMatchModeEnum::RANKED || $match->getStatus() !== GameMatchStatusEnum::WAITING || $this->isBlocked($match)) {
            return;
        }

        $enough = count($match->getPlayers()) >= $this->quickMinimum($match);
        $deadline = $match->getTimeline()['fillEndsAt'] ?? PHP_INT_MAX;

        if ($enough && ($match->isFull() || $this->now() >= $deadline)) {
            $this->start($match);
        }
    }

    public function vote(GameMatch $match, User $user, bool $replay): void
    {
        if ($match->isSolo() || !$match->hasPlayer($user) || $match->getStatus() !== GameMatchStatusEnum::FINISHED || $match->getNextCode() !== null || $this->now() > $match->getTimeline()['voteEndsAt']) {
            throw new GameActionException();
        }

        $players = $match->vote($user, $replay)->getReplayPlayers();

        if (!$this->isBlocked($match) && count($players) >= $this->majority($match) && count($players) >= $match->getGame()->getMinPlayers()) {

            $players = array_merge(array_slice($players, 1), array_slice($players, 0, 1));

            $next = (new GameMatch())
                ->setGame($match->getGame())
                ->setMode($match->getMode())
                ->setRoom($match->getRoom())
                ->setCode($this->code())
                ->setPlayers($players)
                ->setSettings($match->getSettings())
                ->mark('createdAt', $this->now());

            $this->start($next);
            $match->getRoom()?->setStatus(RoomStatusEnum::PLAYING)->setMatchCode($next->getCode())->resetRound()->addHistory($match->getGame()->getId());
            $this->save($next);
            $match->setNextCode($next->getCode());
        }

        $this->save($match);
    }

    public function snapshot(GameMatch $match): array
    {
        $server = $this->games->server($match->getGame());
        $state = $match->getState();
        $started = $state !== [];

        return [
            'status' => $match->getStatus()->value,
            'mode' => $match->getMode()->value,
            'players' => array_map(fn (array $player): array => $player + ['look' => $this->shop->cosmetics((int) ($player['id'] ?? 0))], $match->getPlayers()),
            'maxPlayers' => $match->getGame()->getMaxPlayers(),
            'state' => $started ? $server->view($state) : [],
            'private' => $server->hasSecret(),
            'scoreboard' => $started ? $server->scoreboard($state) : [],
            'progress' => $started ? $server->progress($state) : null,
            'timeline' => $match->getTimeline(),
            'votes' => $match->getVotes(),
            'majority' => $this->majority($match),
            'next' => $match->getNextCode(),
            'results' => $match->isSolo() && $match->getStatus() === GameMatchStatusEnum::FINISHED
                ? array_map(fn (array $result): array => $result + ['points' => $match->getGame()->pointsFor($result['position'])], $server->results($state))
                : null,
            'maintenance' => $this->maintenance->warning($match->getGame()),
            'room' => $match->getRoom()?->getCode(),
            'rewards' => $match->getRewards(),
            'serverTime' => $this->now(),
            'queuePolicy' => $match->getMode() === GameMatchModeEnum::RANKED
                ? [
                    'initial' => RankedQueue::INITIAL_GAP,
                    'step' => RankedQueue::GAP_STEP,
                    'interval' => RankedQueue::STEP_MS,
                    'max' => RankedQueue::MAX_GAP,
                ]
                : null,
        ];
    }

    public function secret(GameMatch $match, ?User $user): array
    {
        $state = $match->getState();

        if ($state === [] || $user === null || !$match->hasPlayer($user)) {
            return [];
        }

        return $this->games->server($match->getGame())->secret($state, (string) $user->getId());
    }

    public function interrupt(GameMatch $match): bool
    {
        if ($match->getStatus() === GameMatchStatusEnum::CANCELLED) {
            return true;
        }

        if ($match->getStatus() !== GameMatchStatusEnum::PLAYING || isset($match->getTimeline()['preview']) || !$this->maintenance->cuts($match)) {
            return false;
        }

        $match->setStatus(GameMatchStatusEnum::CANCELLED)->mark('cutAt', $this->now());
        $this->save($match);

        return true;
    }

    public function isBlocked(GameMatch $match): bool
    {
        return !isset($match->getTimeline()['preview']) && $this->maintenance->blocksNewMatches($match->getGame());
    }

    public function online(): array
    {
        $since = new DateTime('-' . self::ACTIVITY_WINDOW . ' seconds');
        $online = [];

        foreach ([GameMatchStatusEnum::WAITING, GameMatchStatusEnum::PLAYING] as $status) {
            foreach ($this->matches->findBy(['status' => $status]) as $match) {
                if (isset($match->getTimeline()['preview']) || $match->getUpdatedAt() === null || $match->getUpdatedAt() < $since) {
                    continue;
                }

                $game = $match->getGame()->getId();
                $online[$game] = ($online[$game] ?? 0) + count($match->getPlayers());
            }
        }

        return $online;
    }

    public function topic(GameMatch $match): string
    {
        return 'games/matches/' . $match->getCode();
    }

    protected function start(GameMatch $match, int $delay = self::LAUNCH_DELAY): void
    {
        $sdk = $this->games->sdk($match->getGame());
        $settings = $sdk->configure($match->getSettings());
        $startsAt = $this->now() + $delay;

        $match
            ->setSettings($settings)
            ->setState($sdk->init($match->getPlayerIds(), $settings, $startsAt))
            ->setStatus(GameMatchStatusEnum::PLAYING)
            ->mark('startsAt', $startsAt);
    }

    protected function save(GameMatch $match): void
    {
        $this->matches->save($match, true);
        $this->mercure->publish(Update::json($this->topic($match), $this->snapshot($match)));
    }

    protected function purgeStale(Game $game): void
    {
        foreach ($this->matches->findBy(['game' => $game, 'status' => GameMatchStatusEnum::WAITING]) as $match) {
            if (!in_array($match->getMode(), [GameMatchModeEnum::ROOM, GameMatchModeEnum::RANKED], true) && $this->now() - ($match->getTimeline()['createdAt'] ?? 0) >= self::STALE_AFTER) {
                $this->matches->delete($match);
            }
        }

        $this->matches->getOrm()->flush();
    }

    protected function quickMinimum(GameMatch $match): int
    {
        return min($match->getGame()->getMaxPlayers(), max(self::QUICK_MINIMUM, $match->getGame()->getMinPlayers()));
    }

    protected function majority(GameMatch $match): int
    {
        return $match->getRoom() === null ? count($match->getPlayers()) : intdiv(count($match->getPlayers()), 2) + 1;
    }

    protected function code(): string
    {
        do {
            $code = strtoupper(bin2hex(random_bytes(3)));
        } while ($this->matches->findOneBy(['code' => $code]) !== null);

        return $code;
    }
}
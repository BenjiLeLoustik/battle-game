<?php

namespace App\Shared\Badge;

use App\Entity\Game;
use App\Entity\User;
use App\Repository\GameRepository;
use App\Repository\GameResultRepository;
use App\Shared\User\LevelManager;
use NeoPHP\Component\Database\Contract\ConnectionInterface;

class BadgeStats
{
    protected array $cache = [];

    public function __construct(
        protected GameResultRepository $results,
        protected GameRepository $games,
        protected LevelManager $levels,
    ) {
    }

    public function reset(): void
    {
        $this->cache = [];
    }

    public function played(User $user, ?Game $game): int
    {
        return (int) $this->remember('played', $user, $game, fn (): mixed => $this->scalar('COUNT(*)', $user, $game));
    }

    public function wins(User $user, ?Game $game): int
    {
        return (int) $this->remember('wins', $user, $game, fn (): mixed => $this->scalar('COALESCE(SUM(won), 0)', $user, $game));
    }

    public function points(User $user, ?Game $game): int
    {
        return (int) $this->remember('points', $user, $game, fn (): mixed => $this->scalar('COALESCE(SUM(points), 0)', $user, $game));
    }

    public function level(User $user, ?Game $game): int
    {
        return $this->levels->level($this->points($user, $game));
    }

    public function streak(User $user, ?Game $game): int
    {
        return (int) $this->remember('streak', $user, $game, function () use ($user, $game): int {
            [$where, $params] = $this->where($user, $game);
            $results = $this->connection()->fetchFirstColumn('SELECT won FROM game_result WHERE ' . $where . ' ORDER BY id DESC LIMIT 100', $params);
            $streak = 0;

            foreach ($results as $won) {
                if (!(bool) $won) {
                    break;
                }

                $streak++;
            }

            return $streak;
        });
    }

    public function friends(User $user): int
    {
        return (int) $this->remember('friends', $user, null, fn (): mixed => $this->connection()->fetchOne(
            'SELECT COUNT(*) FROM friendship WHERE status = :status AND (requester_id = :user OR addressee_id = :user)',
            ['status' => 'accepted', 'user' => $user->getId()],
        ));
    }

    public function distinctGames(User $user): int
    {
        return (int) $this->remember('distinct', $user, null, fn (): mixed => $this->scalar('COUNT(DISTINCT game_id)', $user, null));
    }

    public function liveGames(): int
    {
        return $this->cache['live'] ??= count($this->games->findLive());
    }

    public function best(User $user, Game $game, string $key, ?string $aggregate = null): ?float
    {
        $stat = current(array_filter($game->getStats(), fn (array $stat): bool => $stat['key'] === $key)) ?: null;

        if ($stat === null) {
            return null;
        }

        $stat['aggregate'] = in_array($aggregate, ['max', 'min', 'sum', 'avg'], true) ? $aggregate : $stat['aggregate'];

        return $this->remember('best.' . $key . '.' . $stat['aggregate'], $user, $game, function () use ($user, $game, $stat): ?float {
            $value = $this->results->summary($game, $user, [$stat])[$stat['key']] ?? null;

            return $value === null ? null : (float) $value;
        });
    }

    protected function scalar(string $expression, User $user, ?Game $game): mixed
    {
        [$where, $params] = $this->where($user, $game);

        return $this->connection()->fetchOne('SELECT ' . $expression . ' FROM game_result WHERE ' . $where, $params);
    }

    protected function where(User $user, ?Game $game): array
    {
        return $game === null
            ? ['user_id = :user', ['user' => $user->getId()]]
            : ['user_id = :user AND game_id = :game', ['user' => $user->getId(), 'game' => $game->getId()]];
    }

    protected function remember(string $name, User $user, ?Game $game, callable $resolver): mixed
    {
        $key = sprintf('%s.%d.%d', $name, $user->getId(), $game?->getId() ?? 0);

        if (!array_key_exists($key, $this->cache)) {
            $this->cache[$key] = $resolver();
        }

        return $this->cache[$key];
    }

    protected function connection(): ConnectionInterface
    {
        return $this->results->getOrm()->getConnection();
    }
}

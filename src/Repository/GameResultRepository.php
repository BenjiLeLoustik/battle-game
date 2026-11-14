<?php

namespace App\Repository;

use App\Entity\Game;
use App\Entity\GameResult;
use App\Entity\User;
use DateTime;
use NeoPHP\Component\Database\Contract\ConnectionInterface;
use NeoPHP\Package\Orm\Contract\AbstractRepository;

class GameResultRepository extends AbstractRepository
{
    protected string $entityClass = GameResult::class;

    public function summary(Game $game, User $user, array $stats): array
    {
        $columns = array_map(fn (array $stat): string => sprintf('%s(%s) AS `%s`', $this->aggregate($stat), $this->value($stat), $stat['key']), $stats);

        return $this->connection()->fetchAssociative(
            'SELECT ' . implode(', ', ['COUNT(*) AS played', 'SUM(r.won) AS won', ...$columns]) . ' FROM game_result r WHERE r.game_id = :game AND r.user_id = :user',
            ['game' => $game->getId(), 'user' => $user->getId()],
        ) ?? [];
    }

    public function totals(User $user): array
    {
        return $this->connection()->fetchAssociative(
            'SELECT COUNT(*) AS played, COALESCE(SUM(r.won), 0) AS won, COALESCE(SUM(r.points), 0) AS points FROM game_result r WHERE r.user_id = :user',
            ['user' => $user->getId()],
        ) ?? ['played' => 0, 'won' => 0, 'points' => 0];
    }

    public function pointsOf(User $user, ?Game $game): int
    {
        return (int) $this->connection()->fetchOne(
            'SELECT COALESCE(SUM(points), 0) FROM game_result WHERE user_id = :user' . ($game === null ? '' : ' AND game_id = :game'),
            ['user' => $user->getId()] + ($game === null ? [] : ['game' => $game->getId()]),
        );
    }

    public function perGame(User $user): array
    {
        return $this->connection()->fetchAllAssociative(
            'SELECT r.game_id, COUNT(*) AS played, SUM(r.won) AS won, SUM(r.points) AS points, MAX(r.created_at) AS last FROM game_result r WHERE r.user_id = :user GROUP BY r.game_id ORDER BY points DESC',
            ['user' => $user->getId()],
        );
    }

    public function recent(User $user, int $limit): array
    {
        return $this->findBy(['user' => $user], ['id' => 'DESC'], $limit);
    }

    public function globalRank(User $user, int $points): int
    {
        return 1 + (int) $this->connection()->fetchOne(
            'SELECT COUNT(*) FROM (SELECT SUM(r.points) AS total FROM game_result r JOIN `user` u ON u.id = r.user_id WHERE u.guest = 0 GROUP BY r.user_id) totals WHERE totals.total > :points',
            ['points' => $points],
        );
    }

    public function headToHead(User $user, User $other): array
    {
        return $this->connection()->fetchAllAssociative(
            'SELECT r1.game_id, r1.position AS mine, r2.position AS theirs, r1.created_at FROM game_result r1 JOIN game_result r2 ON r2.match_id = r1.match_id AND r2.user_id = :other WHERE r1.user_id = :user ORDER BY r1.id DESC',
            ['user' => $user->getId(), 'other' => $other->getId()],
        );
    }

    public function leaderboard(Game $game, array $stat, int $limit): array
    {
        return $this->connection()->fetchAllAssociative(
            sprintf(
                'SELECT u.id, u.username, %s(%s) AS best FROM game_result r JOIN `user` u ON u.id = r.user_id WHERE r.game_id = :game AND u.guest = 0 GROUP BY u.id, u.username HAVING best IS NOT NULL ORDER BY best %s LIMIT %d',
                $this->aggregate($stat),
                $this->value($stat),
                $this->direction($stat),
                $limit,
            ),
            ['game' => $game->getId()],
        );
    }

    public function rank(Game $game, array $stat, string $best): int
    {
        return 1 + (int) $this->connection()->fetchOne(
                sprintf(
                    'SELECT COUNT(*) FROM (SELECT %s(%s) AS best FROM game_result r JOIN `user` u ON u.id = r.user_id WHERE r.game_id = :game AND u.guest = 0 GROUP BY r.user_id) scores WHERE scores.best %s :best',
                    $this->aggregate($stat),
                    $this->value($stat),
                    $this->direction($stat) === 'ASC' ? '<' : '>',
                ),
                ['game' => $game->getId(), 'best' => $best],
            );
    }

    public function ranking(?Game $game, ?DateTime $since, ?DateTime $until): array
    {
        [$conditions, $params] = $this->filters($game, $since, $until);
        $conditions[] = 'u.guest = 0';

        return $this->connection()->fetchAllAssociative(
            'SELECT u.id, u.username, u.first_name, u.last_name, u.avatar, JSON_EXTRACT(u.preferences, \'$.privacy.leaderboard\') AS listed, SUM(r.points) AS points, COUNT(*) AS games, SUM(r.won) AS wins'
            . ' FROM game_result r JOIN `user` u ON u.id = r.user_id' . $this->where($conditions)
            . ' GROUP BY u.id, u.username, u.first_name, u.last_name, u.avatar, u.preferences ORDER BY points DESC, wins DESC, u.id ASC',
            $params,
        );
    }

    public function experience(array $users): array
    {
        if ($users === []) {
            return [];
        }

        return $this->connection()->fetchAllKeyValue(
            'SELECT r.user_id, SUM(r.points) FROM game_result r WHERE r.user_id IN (:users) GROUP BY r.user_id',
            ['users' => $users],
        );
    }

    public function favorites(array $users, ?DateTime $since, ?DateTime $until): array
    {
        if ($users === []) {
            return [];
        }

        [$conditions, $params] = $this->filters(null, $since, $until);
        $conditions[] = 'r.user_id IN (:users)';
        $favorites = [];

        $rows = $this->connection()->fetchAllAssociative(
            'SELECT r.user_id, g.name, COUNT(*) AS total FROM game_result r JOIN game g ON g.id = r.game_id' . $this->where($conditions)
            . ' GROUP BY r.user_id, g.id, g.name ORDER BY total DESC',
            $params + ['users' => $users],
        );

        foreach ($rows as $row) {
            $favorites[$row['user_id']] ??= $row['name'];
        }

        return $favorites;
    }

    protected function filters(?Game $game, ?DateTime $since, ?DateTime $until): array
    {
        $conditions = [];
        $params = [];

        if ($game !== null) {
            $conditions[] = 'r.game_id = :game';
            $params['game'] = $game->getId();
        }

        if ($since !== null) {
            $conditions[] = 'r.created_at >= :since';
            $params['since'] = $since->format('Y-m-d H:i:s');
        }

        if ($until !== null) {
            $conditions[] = 'r.created_at < :until';
            $params['until'] = $until->format('Y-m-d H:i:s');
        }

        return [$conditions, $params];
    }

    protected function where(array $conditions): string
    {
        return $conditions === [] ? '' : ' WHERE ' . implode(' AND ', $conditions);
    }

    protected function value(array $stat): string
    {
        return sprintf('CAST(JSON_EXTRACT(r.stats, \'$.%s\') AS DECIMAL(12,2))', $stat['key']);
    }

    protected function aggregate(array $stat): string
    {
        return strtoupper($stat['aggregate']);
    }

    protected function direction(array $stat): string
    {
        return $stat['aggregate'] === 'max' ? 'DESC' : 'ASC';
    }

    protected function connection(): ConnectionInterface
    {
        return $this->getOrm()->getConnection();
    }
}
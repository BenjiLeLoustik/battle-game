<?php

namespace App\Admin\Dashboard;

use App\Repository\GameRepository;
use App\Repository\UserRepository;
use App\Shared\Social\PresenceManager;
use DateTime;
use NeoPHP\Component\Container\Attribute\Autowire;
use NeoPHP\Component\Database\Contract\ConnectionInterface;
use NeoPHP\Package\Mercure\MercureManagerInterface;
use NeoPHP\Package\Mercure\Model\Update;
use Throwable;

class DashboardManager
{
    public const PERIODS = ['day' => '-24 hours', 'week' => '-7 days', 'month' => '-30 days'];

    public function __construct(
        protected UserRepository $users,
        protected GameRepository $games,
        protected PresenceManager $presence,
        protected MercureManagerInterface $mercure,
        #[Autowire(env: 'DISCORD_CLIENT_ID')]
        protected ?string $discordClient = null,
    ) {
    }

    public function kpis(string $period): array
    {
        $since = $this->since($period);

        return [
            'online' => $this->presence->countOnline(),
            'accounts' => (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM `user` WHERE guest = 0 AND created_at >= :since', ['since' => $since]),
            'discord' => (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM `user` WHERE guest = 0 AND discord_id IS NOT NULL AND created_at >= :since', ['since' => $since]),
            'rooms' => (int) $this->connection()->fetchOne('SELECT COUNT(DISTINCT room_id) FROM room_member WHERE seen_at >= :since', ['since' => (time() - 3600) * 1000]),
            'private_rooms' => (int) $this->connection()->fetchOne('SELECT COUNT(DISTINCT r.id) FROM room r JOIN room_member m ON m.room_id = r.id WHERE m.seen_at >= :since AND r.visibility <> :public', ['since' => (time() - 3600) * 1000, 'public' => 'public']),
            'matches' => (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM game_match WHERE created_at >= :since AND mode <> :solo', ['since' => $since, 'solo' => 'solo']),
            'reports' => (int) $this->connection()->fetchOne('SELECT COUNT(*) FROM report WHERE status = :open', ['open' => 'open']),
        ];
    }

    public function activity(): array
    {
        $start = new DateTime(date('Y-m-d H:00:00', strtotime('-23 hours')));
        $rows = $this->connection()->fetchAllAssociative(
            'SELECT r.slot, SUM(u.guest = 0) AS members, SUM(u.guest = 1) AS guests FROM ('
            . 'SELECT DISTINCT user_id, DATE_FORMAT(created_at, \'%Y-%m-%d %H\') AS slot FROM game_result WHERE created_at >= :since'
            . ') r JOIN `user` u ON u.id = r.user_id GROUP BY r.slot',
            ['since' => $start->format('Y-m-d H:i:s')],
        );
        $counts = array_column($rows, null, 'slot');
        $hours = [];

        for ($index = 0; $index < 24; $index++) {
            $slot = (clone $start)->modify('+' . $index . ' hours');
            $members = (int) ($counts[$slot->format('Y-m-d H')]['members'] ?? 0);
            $guests = (int) ($counts[$slot->format('Y-m-d H')]['guests'] ?? 0);
            $hours[] = ['hour' => (int) $slot->format('G'), 'members' => $members, 'guests' => $guests, 'total' => $members + $guests];
        }

        $totals = $this->connection()->fetchAssociative(
            'SELECT COALESCE(SUM(u.guest = 0), 0) AS members, COALESCE(SUM(u.guest = 1), 0) AS guests FROM (SELECT DISTINCT user_id FROM game_result WHERE created_at >= :since) r JOIN `user` u ON u.id = r.user_id',
            ['since' => $start->format('Y-m-d H:i:s')],
        ) ?: [];
        $peak = array_reduce($hours, fn (?array $best, array $hour): ?array => $hour['total'] > ($best['total'] ?? 0) ? $hour : $best);
        $max = max(4, $peak['total'] ?? 0);

        return [
            'hours' => $hours,
            'max' => $max + $max % 2,
            'peak' => $peak,
            'members' => (int) ($totals['members'] ?? 0),
            'guests' => (int) ($totals['guests'] ?? 0),
        ];
    }

    public function topGames(string $period): array
    {
        return $this->connection()->fetchAllAssociative(
            'SELECT g.name, COUNT(*) AS games, SUM(m.status = :cancelled) AS cancelled,'
            . ' AVG(CASE WHEN m.status = :finished THEN JSON_EXTRACT(m.timeline, \'$.resultsAt\') - JSON_EXTRACT(m.timeline, \'$.startsAt\') END) AS duration'
            . ' FROM game_match m JOIN game g ON g.id = m.game_id WHERE m.created_at >= :since AND m.mode <> :solo GROUP BY g.id, g.name ORDER BY games DESC LIMIT 5',
            ['since' => $this->since($period), 'cancelled' => 'cancelled', 'finished' => 'finished', 'solo' => 'solo'],
        );
    }

    public function services(): array
    {
        $start = microtime(true);
        $this->connection()->fetchOne('SELECT 1');
        $database = (int) round((microtime(true) - $start) * 1000);

        try {
            $start = microtime(true);
            $this->mercure->publish(Update::json('admin/ping', ['at' => time()]));
            $mercure = ['ok' => true, 'value' => (int) round((microtime(true) - $start) * 1000) . ' ms'];
        } catch (Throwable) {
            $mercure = ['ok' => false, 'value' => null];
        }

        return [
            'app' => ['ok' => true, 'value' => PHP_VERSION],
            'mercure' => $mercure,
            'database' => ['ok' => true, 'value' => $database . ' ms'],
            'discord' => ['ok' => (string) $this->discordClient !== '', 'value' => null],
        ];
    }

    protected function since(string $period): string
    {
        return (new DateTime(self::PERIODS[$period] ?? self::PERIODS['day']))->format('Y-m-d H:i:s');
    }

    protected function connection(): ConnectionInterface
    {
        return $this->users->getOrm()->getConnection();
    }
}

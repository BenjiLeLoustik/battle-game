<?php

namespace App\Repository;

use App\Entity\User;
use DateTime;
use NeoPHP\Package\Orm\Contract\AbstractRepository;

class UserRepository extends AbstractRepository
{
    protected string $entityClass = User::class;

    public function findAdmins(): array
    {
        $ids = $this->getOrm()->getConnection()->fetchFirstColumn(
            'SELECT id FROM `user` WHERE JSON_CONTAINS(roles, :role) ORDER BY username',
            ['role' => '"ROLE_ADMIN"'],
        );

        return array_values(
            array_filter(
                array_map(fn (mixed $id): ?User => $this->find((int) $id), $ids)
            )
        );
    }

    public function purgeGuests(DateTime $before): int
    {
        $connection = $this->getOrm()->getConnection();
        $params = ['before' => $before->format('Y-m-d H:i:s'), 'used' => $before->getTimestamp(), 'domain' => '%@' . User::GUEST_DOMAIN];

        $deleted = $connection->executeStatement(
            'DELETE FROM `user` WHERE guest = 1 AND created_at < :before'
            . ' AND id NOT IN (SELECT user_id FROM game_result WHERE created_at >= :before)'
            . ' AND BINARY email NOT IN (SELECT BINARY identifier FROM remember_me_tokens WHERE last_used_at >= :used)',
            $params,
        );

        $connection->executeStatement(
            'DELETE FROM remember_me_tokens WHERE identifier LIKE :domain AND BINARY identifier NOT IN (SELECT BINARY email FROM `user`)',
            ['domain' => $params['domain']],
        );

        return $deleted;
    }

    public function search(array $filters, int $page, int $size): array
    {
        $conditions = ['u.guest = 0'];
        $params = [];
        $now = (new DateTime())->format('Y-m-d H:i:s');
        $lockout = 'EXISTS (SELECT 1 FROM sanction s WHERE s.user_id = u.id AND s.type = :%s AND s.revoked_at IS NULL AND (s.ends_at IS NULL OR s.ends_at > :now))';

        if (($filters['q'] ?? '') !== '') {
            $conditions[] = '(u.username LIKE :q OR u.email LIKE :q)';
            $params['q'] = '%' . $filters['q'] . '%';
        }

        if (in_array($filters['status'] ?? '', ['suspension', 'ban'], true)) {
            $conditions[] = sprintf($lockout, 'type');
            $params += ['type' => $filters['status'], 'now' => $now];
        }

        if (($filters['status'] ?? '') === 'active') {
            $conditions[] = 'NOT ' . sprintf($lockout, 'suspension') . ' AND NOT ' . sprintf($lockout, 'ban');
            $params += ['suspension' => 'suspension', 'ban' => 'ban', 'now' => $now];
        }

        $conditions[] = match ($filters['auth'] ?? '') {
            'email' => 'u.discord_id IS NULL',
            'discord' => 'u.discord_id IS NOT NULL AND u.password IS NULL',
            'both' => 'u.discord_id IS NOT NULL AND u.password IS NOT NULL',
            default => '1 = 1',
        };

        $where = ' WHERE ' . implode(' AND ', $conditions);
        $connection = $this->getOrm()->getConnection();
        $total = (int) $connection->fetchOne('SELECT COUNT(*) FROM `user` u' . $where, $params);
        $ids = $connection->fetchFirstColumn(sprintf('SELECT u.id FROM `user` u%s ORDER BY u.id DESC LIMIT %d OFFSET %d', $where, $size, ($page - 1) * $size), $params);

        return [array_values(array_filter(array_map(fn (mixed $id): ?User => $this->find((int) $id), $ids))), $total];
    }

    public function findPlayers(string $query, int $limit): array
    {
        $ids = $this->getOrm()->getConnection()->fetchFirstColumn(
            sprintf('SELECT id FROM `user` WHERE guest = 0 AND deletion_requested_at IS NULL AND username LIKE :contains ORDER BY username LIKE :starts DESC, username ASC LIMIT %d', $limit),
            ['contains' => '%' . addcslashes($query, '%_\\') . '%', 'starts' => addcslashes($query, '%_\\') . '%'],
        );

        return array_values(array_filter(array_map(fn (mixed $id): ?User => $this->find((int) $id), $ids)));
    }

    public function findDeletionDue(DateTime $before): array
    {
        $ids = $this->getOrm()->getConnection()->fetchFirstColumn(
            'SELECT id FROM `user` WHERE deletion_requested_at IS NOT NULL AND deletion_requested_at < :before AND username NOT LIKE :deleted',
            ['before' => $before->format('Y-m-d H:i:s'), 'deleted' => 'deleted\_%'],
        );

        return array_values(array_filter(array_map(fn (mixed $id): ?User => $this->find((int) $id), $ids)));
    }
}

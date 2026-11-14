<?php

namespace App\Admin\Reset;

use App\Entity\User;
use App\Repository\UserRepository;
use NeoPHP\Component\Container\Attribute\Autowire;

class ResetManager
{
    public const BLOCKS = [
        'activity' => [
            'tables' => ['game_result', 'game_match', 'user_badge', 'room_message', 'room_member', 'room'],
        ],
        'social' => [
            'tables' => ['chat_message', 'chat_read', 'friendship', 'user_block', 'notification', 'report', 'sanction'],
        ],
        'shop' => [
            'tables' => ['shop_order', 'coin_transaction', 'wallet', 'subscription', 'user_item'],
        ],
        'catalog' => [
            'tables' => ['user_item', 'shop_product'],
            'translations' => ['shopproduct'],
        ],
        'players' => [
            'tables' => [],
        ],
        'content' => [
            'tables' => ['banner', 'banner_list', 'page', 'badge', 'user_badge', 'season', 'maintenance', 'email_campaign', 'mailing_list'],
            'translations' => ['banner', 'page', 'badge', 'season', 'maintenance', 'emailcampaign'],
        ],
        'settings' => [
            'tables' => ['setting'],
        ],
        'logs' => [
            'tables' => ['audit_log', 'neo_queue_failed'],
        ],
    ];

    public function __construct(
        protected UserRepository $users,
        #[Autowire(param: 'kernel.public_path')]
        protected string $publicPath,
    ) {
    }

    public function blocks(): array
    {
        $connection = $this->users->getOrm()->getConnection();
        $existing = $connection->fetchFirstColumn('SHOW TABLES');
        $blocks = [];

        foreach (self::BLOCKS as $key => $block) {
            $counts = [];

            foreach ($block['tables'] as $table) {
                if (in_array($table, $existing, true)) {
                    $counts[$table] = (int) $connection->fetchOne('SELECT COUNT(*) FROM `' . $table . '`');
                }
            }

            if ($key === 'players') {
                $counts['user'] = count($this->players());
            }

            $blocks[$key] = ['counts' => $counts, 'total' => array_sum($counts)];
        }

        return $blocks;
    }

    public function reset(array $keys): array
    {
        $keys = array_values(array_intersect(array_keys(self::BLOCKS), $keys));
        $connection = $this->users->getOrm()->getConnection();
        $existing = $connection->fetchFirstColumn('SHOW TABLES');
        $deleted = [];
        $connection->executeStatement('SET FOREIGN_KEY_CHECKS=0');

        try {
            foreach ($keys as $key) {
                if ($key === 'players') {
                    $deleted['user'] = $this->deletePlayers();
                }

                foreach (self::BLOCKS[$key]['tables'] as $table) {
                    if (in_array($table, $existing, true) && !isset($deleted[$table])) {
                        $deleted[$table] = $connection->executeStatement('DELETE FROM `' . $table . '`');
                    }
                }

                foreach (self::BLOCKS[$key]['translations'] ?? [] as $type) {
                    $connection->executeStatement('DELETE FROM content_translation WHERE type = :type', ['type' => $type]);
                }
            }
        } finally {
            $connection->executeStatement('SET FOREIGN_KEY_CHECKS=1');
        }

        $this->users->getOrm()->clear();

        return $deleted;
    }

    protected function players(): array
    {
        return array_values(array_filter($this->users->findAll(), fn (User $user): bool => !$user->isAdmin()));
    }

    protected function deletePlayers(): int
    {
        $players = $this->players();

        if ($players === []) {
            return 0;
        }

        foreach ($players as $player) {
            $avatar = $player->getAvatar();

            if ($avatar !== null && str_starts_with($avatar, '/uploads/avatars/') && is_file($this->publicPath . $avatar)) {
                unlink($this->publicPath . $avatar);
            }
        }

        $ids = array_map(fn (User $user): int => (int) $user->getId(), $players);
        $placeholders = implode(', ', array_fill(0, count($ids), '?'));
        $connection = $this->users->getOrm()->getConnection();

        foreach ($connection->fetchAllAssociative("SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME = 'user'") as $reference) {
            if ($reference['TABLE_NAME'] !== 'user') {
                $connection->executeStatement(sprintf('DELETE FROM `%s` WHERE `%s` IN (%s)', $reference['TABLE_NAME'], $reference['COLUMN_NAME'], $placeholders), $ids);
            }
        }

        return $connection->executeStatement('DELETE FROM `user` WHERE id IN (' . $placeholders . ')', $ids);
    }
}

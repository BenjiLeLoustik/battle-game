<?php

namespace App\Site\Account;

use App\Entity\User;
use App\Repository\UserRepository;

class DataExporter
{
    private const HIDDEN = ['password', 'totp_secret', 'totp_recovery', 'email_token', 'reset_token', 'token', 'series', 'value', 'secret'];
    private const SKIP = ['remember_me_tokens', 'audit_log'];

    public function __construct(protected UserRepository $users)
    {
    }

    public function export(User $user): array
    {
        $connection = $this->users->getOrm()->getConnection();
        $account = $connection->fetchAssociative('SELECT * FROM `user` WHERE id = :id', ['id' => $user->getId()]) ?? [];
        $data = [
            'generated_at' => date(DATE_ATOM),
            'notice' => 'Export de tes données personnelles BattleGame (RGPD, droit d\'accès et à la portabilité).',
            'account' => $this->clean($account),
        ];

        foreach ($connection->fetchAllAssociative("SELECT TABLE_NAME, COLUMN_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = DATABASE() AND REFERENCED_TABLE_NAME = 'user' ORDER BY TABLE_NAME") as $reference) {
            $table = $reference['TABLE_NAME'];

            if ($table === 'user' || in_array($table, self::SKIP, true)) {
                continue;
            }

            $rows = $connection->fetchAllAssociative(sprintf('SELECT * FROM `%s` WHERE `%s` = :id', $table, $reference['COLUMN_NAME']), ['id' => $user->getId()]);

            if ($rows !== []) {
                $data[$table . ($reference['COLUMN_NAME'] !== 'user_id' ? ' (' . $reference['COLUMN_NAME'] . ')' : '')] = array_map($this->clean(...), $rows);
            }
        }

        return $data;
    }

    protected function clean(array $row): array
    {
        foreach ($row as $key => $value) {
            if (in_array($key, self::HIDDEN, true) || str_contains($key, 'token') || str_contains($key, 'secret') || str_contains($key, 'password')) {
                unset($row[$key]);
            } elseif (is_string($value) && ($value[0] ?? '') !== '' && in_array($value[0], ['{', '['], true)) {
                $decoded = json_decode($value, true);
                $row[$key] = json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
            }
        }

        return $row;
    }
}

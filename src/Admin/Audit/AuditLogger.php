<?php

namespace App\Admin\Audit;

use App\Entity\AuditLog;
use App\Entity\User;
use App\Repository\AuditLogRepository;
use NeoPHP\Package\Translation\TranslationManagerInterface;

class AuditLogger
{
    public const PERIODS = ['all' => null, 'month' => '-30 days', 'week' => '-7 days', 'day' => '-24 hours'];

    public function __construct(protected AuditLogRepository $logs, protected TranslationManagerInterface $translator)
    {
    }

    public function log(?User $user, string $action, string $detail = ''): void
    {
        $this->logs->save((new AuditLog())->setUser($user)->setAction($action)->setDetail(mb_substr($detail, 0, 255)), true);
    }

    public function latest(int $limit): array
    {
        return $this->logs->findBy([], ['id' => 'DESC'], $limit);
    }

    public function csv(string $period): string
    {
        $since = self::PERIODS[$period] ?? null;
        $rows = $this->logs->getOrm()->getConnection()->fetchAllAssociative(
            'SELECT a.created_at, u.username, a.action, a.detail FROM audit_log a LEFT JOIN `user` u ON u.id = a.user_id'
            . ($since ? ' WHERE a.created_at >= :since' : '') . ' ORDER BY a.id DESC',
            $since ? ['since' => date('Y-m-d H:i:s', strtotime($since))] : [],
        );
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, "\u{FEFF}");
        fputcsv($stream, array_map(fn (string $column): string => $this->translator->translate('admin.system.audit_export.columns.' . $column, [], 'admin'), ['date', 'user', 'action', 'description', 'detail']), ';', '"', '');

        foreach ($rows as $row) {
            $key = 'admin.audit.' . $row['action'];
            fputcsv($stream, [
                $row['created_at'],
                $row['username'] ?? 'system',
                $row['action'],
                $this->translator->has($key, 'admin') ? $this->translator->translate($key, ['detail' => $row['detail']], 'admin') : '',
                $row['detail'],
            ], ';', '"', '');
        }

        rewind($stream);
        $csv = (string) stream_get_contents($stream);
        fclose($stream);

        return $csv;
    }
}

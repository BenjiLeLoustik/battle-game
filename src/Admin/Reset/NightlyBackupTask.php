<?php

namespace App\Admin\Reset;

use App\Shared\Setting\SettingManager;
use NeoPHP\Package\Scheduler\Attribute\AsScheduledTask;

#[AsScheduledTask(cron: '0 3 * * *', name: 'battlegame-backup', timezone: 'Europe/Paris', withoutOverlapping: true, description: 'Sauvegarde automatique de la base et des fichiers')]
class NightlyBackupTask
{
    public function __invoke(BackupManager $backups, SettingManager $settings): string
    {
        if (!$settings->get('backup_auto')) {
            return 'disabled';
        }

        $done = [$backups->create('auto')];

        if ($settings->get('backup_files')) {
            $done[] = $backups->createFiles('auto-fichiers');
        }

        $removed = $backups->prune(max(1, (int) $settings->get('backup_keep')));

        return implode(', ', $done) . ' · ' . $removed . ' removed';
    }
}

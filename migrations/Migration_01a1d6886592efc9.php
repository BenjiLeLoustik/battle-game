<?php

declare(strict_types=1);

namespace Migrations;

use NeoPHP\Package\Orm\Contract\AbstractMigration;

class Migration_01a1d6886592efc9 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(): void
    {
        $this->abortIf($this->getPlatformName() !== 'mysql', 'This migration was generated for mysql.');

        $this->addSql('ALTER TABLE `user` ADD `totp_secret` VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE `user` ADD `totp_enabled_at` DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE `user` ADD `totp_recovery` JSON DEFAULT NULL');
    }

    public function down(): void
    {
        $this->abortIf($this->getPlatformName() !== 'mysql', 'This migration was generated for mysql.');

        $this->addSql('ALTER TABLE `user` DROP `totp_secret`');
        $this->addSql('ALTER TABLE `user` DROP `totp_enabled_at`');
        $this->addSql('ALTER TABLE `user` DROP `totp_recovery`');
    }
}

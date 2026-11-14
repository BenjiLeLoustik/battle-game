<?php

declare(strict_types=1);

namespace Migrations;

use NeoPHP\Package\Orm\Contract\AbstractMigration;

class Migration_01a1cb363b4622dc extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(): void
    {
        $this->abortIf($this->getPlatformName() !== 'mysql', 'This migration was generated for mysql.');

        $this->addSql('ALTER TABLE `user` ADD `reset_token` VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE `user` ADD `reset_expires_at` DATETIME DEFAULT NULL');
    }

    public function down(): void
    {
        $this->abortIf($this->getPlatformName() !== 'mysql', 'This migration was generated for mysql.');

        $this->addSql('ALTER TABLE `user` DROP `reset_token`');
        $this->addSql('ALTER TABLE `user` DROP `reset_expires_at`');
    }
}

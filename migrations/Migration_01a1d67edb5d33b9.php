<?php

declare(strict_types=1);

namespace Migrations;

use NeoPHP\Package\Orm\Contract\AbstractMigration;

class Migration_01a1d67edb5d33b9 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(): void
    {
        $this->abortIf($this->getPlatformName() !== 'mysql', 'This migration was generated for mysql.');

        $this->addSql('ALTER TABLE `season` ADD `rewarded_at` DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE `wallet` ADD `daily_day` DATE DEFAULT NULL');
        $this->addSql('ALTER TABLE `wallet` ADD `daily_streak` INT NOT NULL');
        $this->addSql("ALTER TABLE `wallet` ADD `challenges` JSON NOT NULL DEFAULT '[]'");
    }

    public function down(): void
    {
        $this->abortIf($this->getPlatformName() !== 'mysql', 'This migration was generated for mysql.');

        $this->addSql('ALTER TABLE `season` DROP `rewarded_at`');
        $this->addSql('ALTER TABLE `wallet` DROP `daily_day`');
        $this->addSql('ALTER TABLE `wallet` DROP `daily_streak`');
        $this->addSql('ALTER TABLE `wallet` DROP `challenges`');
    }
}

<?php

declare(strict_types=1);

namespace Migrations;

use NeoPHP\Package\Orm\Contract\AbstractMigration;

class Migration_01a1d9454393cea1 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(): void
    {
        $this->abortIf($this->getPlatformName() !== 'mysql', 'This migration was generated for mysql.');

        $this->addSql('ALTER TABLE `tournament` ADD `rewards` JSON DEFAULT NULL');
        $this->addSql("UPDATE `tournament` SET `rewards` = '[]' WHERE `rewards` IS NULL");
        $this->addSql('ALTER TABLE `tournament` MODIFY `rewards` JSON NOT NULL');
        $this->addSql("ALTER TABLE `tournament` ADD `qualifier` VARCHAR(10) NOT NULL DEFAULT 'open'");
        $this->addSql('ALTER TABLE `tournament` ADD `qualifier_count` INT DEFAULT NULL');
        $this->addSql("ALTER TABLE `tournament` ADD `qualifier_period` VARCHAR(10) NOT NULL DEFAULT 'season'");
        $this->addSql('ALTER TABLE `tournament` ADD `results` JSON DEFAULT NULL');
        $this->addSql("UPDATE `tournament` SET `results` = '[]' WHERE `results` IS NULL");
        $this->addSql('ALTER TABLE `tournament` MODIFY `results` JSON NOT NULL');
    }

    public function down(): void
    {
        $this->abortIf($this->getPlatformName() !== 'mysql', 'This migration was generated for mysql.');

        $this->addSql('ALTER TABLE `tournament` DROP `rewards`');
        $this->addSql('ALTER TABLE `tournament` DROP `qualifier`');
        $this->addSql('ALTER TABLE `tournament` DROP `qualifier_count`');
        $this->addSql('ALTER TABLE `tournament` DROP `qualifier_period`');
        $this->addSql('ALTER TABLE `tournament` DROP `results`');
    }
}

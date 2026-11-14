<?php

declare(strict_types=1);

namespace Migrations;

use NeoPHP\Package\Orm\Contract\AbstractMigration;

class Migration_01a1c53420f9d3da extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(): void
    {
        $this->abortIf($this->getPlatformName() !== 'mysql', 'This migration was generated for mysql.');

        $this->addSql('ALTER TABLE `game_match` ADD `timeline` JSON NOT NULL');
        $this->addSql('ALTER TABLE `game_match` ADD `votes` JSON NOT NULL');
        $this->addSql('ALTER TABLE `game_match` ADD `next_code` VARCHAR(8) DEFAULT NULL');
    }

    public function down(): void
    {
        $this->abortIf($this->getPlatformName() !== 'mysql', 'This migration was generated for mysql.');

        $this->addSql('ALTER TABLE `game_match` DROP `timeline`');
        $this->addSql('ALTER TABLE `game_match` DROP `votes`');
        $this->addSql('ALTER TABLE `game_match` DROP `next_code`');
    }
}

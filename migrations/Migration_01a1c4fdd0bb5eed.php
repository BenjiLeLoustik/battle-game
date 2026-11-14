<?php

declare(strict_types=1);

namespace Migrations;

use NeoPHP\Package\Orm\Contract\AbstractMigration;

class Migration_01a1c4fdd0bb5eed extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(): void
    {
        $this->abortIf($this->getPlatformName() !== 'mysql', 'This migration was generated for mysql.');

        $this->addSql('CREATE TABLE `game` (`name` VARCHAR(80) NOT NULL, `version` VARCHAR(20) NOT NULL, `directory` VARCHAR(100) NOT NULL, `category` VARCHAR(50) NOT NULL, `manifest` JSON NOT NULL, `enabled` TINYINT(1) NOT NULL, `id` INT NOT NULL AUTO_INCREMENT, `slug` VARCHAR(180) NOT NULL, `created_at` DATETIME DEFAULT NULL, `updated_at` DATETIME DEFAULT NULL, PRIMARY KEY (`id`)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE UNIQUE INDEX `UNIQ_1E0A5DB1AFF1FBB5` ON `game` (`slug`)');
        $this->addSql('CREATE TABLE `game_match` (`code` VARCHAR(8) NOT NULL, `status` VARCHAR(20) NOT NULL, `players` JSON NOT NULL, `settings` JSON NOT NULL, `state` JSON NOT NULL, `id` INT NOT NULL AUTO_INCREMENT, `created_at` DATETIME DEFAULT NULL, `updated_at` DATETIME DEFAULT NULL, `game_id` INT NOT NULL, PRIMARY KEY (`id`)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE UNIQUE INDEX `UNIQ_D31A773B61915809` ON `game_match` (`code`)');
        $this->addSql('CREATE INDEX `IDX_B8313645BDA83CF6` ON `game_match` (`game_id`)');
        $this->addSql('ALTER TABLE `game_match` ADD CONSTRAINT `FK_B8313645BDA83CF6` FOREIGN KEY (`game_id`) REFERENCES `game` (`id`) ON DELETE CASCADE');
    }

    public function down(): void
    {
        $this->abortIf($this->getPlatformName() !== 'mysql', 'This migration was generated for mysql.');

        $this->addSql('ALTER TABLE `game_match` DROP FOREIGN KEY `FK_B8313645BDA83CF6`');
        $this->addSql('DROP TABLE `game`');
        $this->addSql('DROP TABLE `game_match`');
    }
}

<?php

declare(strict_types=1);

namespace Migrations;

use NeoPHP\Package\Orm\Contract\AbstractMigration;

class Migration_01a1cad8b523852d extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(): void
    {
        $this->abortIf($this->getPlatformName() !== 'mysql', 'This migration was generated for mysql.');

        $this->addSql('CREATE TABLE `maintenance` (`title` VARCHAR(120) NOT NULL, `target` VARCHAR(20) NOT NULL, `games` JSON NOT NULL, `features` JSON NOT NULL, `starts_at` DATETIME NOT NULL, `ends_at` DATETIME NOT NULL, `match_policy` VARCHAR(20) NOT NULL, `block_before` TINYINT(1) NOT NULL, `pages` JSON NOT NULL, `paths` JSON NOT NULL, `notice` INT NOT NULL, `message` LONGTEXT NOT NULL, `ended_at` DATETIME DEFAULT NULL, `cancelled_at` DATETIME DEFAULT NULL, `id` INT NOT NULL AUTO_INCREMENT, `created_at` DATETIME DEFAULT NULL, `updated_at` DATETIME DEFAULT NULL, `author_id` INT DEFAULT NULL, PRIMARY KEY (`id`)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE INDEX `IDX_16ED2F4E2144DD59` ON `maintenance` (`author_id`)');
        $this->addSql('ALTER TABLE `maintenance` ADD CONSTRAINT `FK_16ED2F4E2144DD59` FOREIGN KEY (`author_id`) REFERENCES `user` (`id`) ON DELETE SET NULL');
    }

    public function down(): void
    {
        $this->abortIf($this->getPlatformName() !== 'mysql', 'This migration was generated for mysql.');

        $this->addSql('ALTER TABLE `maintenance` DROP FOREIGN KEY `FK_16ED2F4E2144DD59`');
        $this->addSql('DROP TABLE `maintenance`');
    }
}

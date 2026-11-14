<?php

declare(strict_types=1);

namespace Migrations;

use NeoPHP\Package\Orm\Contract\AbstractMigration;

class Migration_01a1c968d6ffd0c7 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(): void
    {
        $this->abortIf($this->getPlatformName() !== 'mysql', 'This migration was generated for mysql.');

        $this->addSql('CREATE TABLE `game_member` (`role` VARCHAR(20) NOT NULL, `id` INT NOT NULL AUTO_INCREMENT, `created_at` DATETIME DEFAULT NULL, `updated_at` DATETIME DEFAULT NULL, `game_id` INT NOT NULL, `user_id` INT NOT NULL, PRIMARY KEY (`id`)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE INDEX `IDX_7CB5CEF8308F8D78` ON `game_member` (`game_id`)');
        $this->addSql('CREATE INDEX `IDX_3B385B3E7810E356` ON `game_member` (`user_id`)');
        $this->addSql('ALTER TABLE `game` ADD `status` VARCHAR(20) NOT NULL');
        $this->addSql('ALTER TABLE `game` ADD `history` JSON NOT NULL');
        $this->addSql('ALTER TABLE `game` DROP `enabled`');
        $this->addSql('ALTER TABLE `game_member` ADD CONSTRAINT `FK_7CB5CEF8308F8D78` FOREIGN KEY (`game_id`) REFERENCES `game` (`id`) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE `game_member` ADD CONSTRAINT `FK_3B385B3E7810E356` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE');
    }

    public function down(): void
    {
        $this->abortIf($this->getPlatformName() !== 'mysql', 'This migration was generated for mysql.');

        $this->addSql('ALTER TABLE `game_member` DROP FOREIGN KEY `FK_7CB5CEF8308F8D78`');
        $this->addSql('ALTER TABLE `game_member` DROP FOREIGN KEY `FK_3B385B3E7810E356`');
        $this->addSql('DROP TABLE `game_member`');
        $this->addSql('ALTER TABLE `game` ADD `enabled` TINYINT(1) NOT NULL');
        $this->addSql('ALTER TABLE `game` DROP `status`');
        $this->addSql('ALTER TABLE `game` DROP `history`');
    }
}

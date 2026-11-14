<?php

declare(strict_types=1);

namespace Migrations;

use NeoPHP\Package\Orm\Contract\AbstractMigration;

class Migration_01a1c588dbc207f2 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(): void
    {
        $this->abortIf($this->getPlatformName() !== 'mysql', 'This migration was generated for mysql.');

        $this->addSql('CREATE TABLE `game_result` (`position` INT NOT NULL, `won` TINYINT(1) NOT NULL, `stats` JSON NOT NULL, `id` INT NOT NULL AUTO_INCREMENT, `created_at` DATETIME DEFAULT NULL, `updated_at` DATETIME DEFAULT NULL, `match_id` INT NOT NULL, `game_id` INT NOT NULL, `user_id` INT NOT NULL, PRIMARY KEY (`id`)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE INDEX `IDX_C626C9A2E39311C0` ON `game_result` (`match_id`)');
        $this->addSql('CREATE INDEX `IDX_EE4F21B37CBD8E77` ON `game_result` (`game_id`)');
        $this->addSql('CREATE INDEX `IDX_B28F9A3C46C12E2E` ON `game_result` (`user_id`)');
        $this->addSql('ALTER TABLE `game_result` ADD CONSTRAINT `FK_C626C9A2E39311C0` FOREIGN KEY (`match_id`) REFERENCES `game_match` (`id`) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE `game_result` ADD CONSTRAINT `FK_EE4F21B37CBD8E77` FOREIGN KEY (`game_id`) REFERENCES `game` (`id`) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE `game_result` ADD CONSTRAINT `FK_B28F9A3C46C12E2E` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE');
    }

    public function down(): void
    {
        $this->abortIf($this->getPlatformName() !== 'mysql', 'This migration was generated for mysql.');

        $this->addSql('ALTER TABLE `game_result` DROP FOREIGN KEY `FK_C626C9A2E39311C0`');
        $this->addSql('ALTER TABLE `game_result` DROP FOREIGN KEY `FK_EE4F21B37CBD8E77`');
        $this->addSql('ALTER TABLE `game_result` DROP FOREIGN KEY `FK_B28F9A3C46C12E2E`');
        $this->addSql('DROP TABLE `game_result`');
    }
}

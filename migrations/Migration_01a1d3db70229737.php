<?php

declare(strict_types=1);

namespace Migrations;

use NeoPHP\Package\Orm\Contract\AbstractMigration;

class Migration_01a1d3db70229737 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(): void
    {
        $this->abortIf($this->getPlatformName() !== 'mysql', 'This migration was generated for mysql.');

        $this->addSql('CREATE TABLE `game_review` (`status` VARCHAR(20) NOT NULL, `message` LONGTEXT DEFAULT NULL, `comment` LONGTEXT DEFAULT NULL, `version` VARCHAR(20) DEFAULT NULL, `reviewed_at` DATETIME DEFAULT NULL, `id` INT NOT NULL AUTO_INCREMENT, `created_at` DATETIME DEFAULT NULL, `updated_at` DATETIME DEFAULT NULL, `game_id` INT NOT NULL, `requester_id` INT DEFAULT NULL, `reviewer_id` INT DEFAULT NULL, PRIMARY KEY (`id`)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE INDEX `IDX_83793FD1FCBBD009` ON `game_review` (`game_id`)');
        $this->addSql('CREATE INDEX `IDX_D6514C34FE673D3E` ON `game_review` (`requester_id`)');
        $this->addSql('CREATE INDEX `IDX_18AFBE2370478435` ON `game_review` (`reviewer_id`)');
        $this->addSql('ALTER TABLE `game_review` ADD CONSTRAINT `FK_83793FD1FCBBD009` FOREIGN KEY (`game_id`) REFERENCES `game` (`id`) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE `game_review` ADD CONSTRAINT `FK_D6514C34FE673D3E` FOREIGN KEY (`requester_id`) REFERENCES `user` (`id`) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE `game_review` ADD CONSTRAINT `FK_18AFBE2370478435` FOREIGN KEY (`reviewer_id`) REFERENCES `user` (`id`) ON DELETE SET NULL');
    }

    public function down(): void
    {
        $this->abortIf($this->getPlatformName() !== 'mysql', 'This migration was generated for mysql.');

        $this->addSql('ALTER TABLE `game_review` DROP FOREIGN KEY `FK_83793FD1FCBBD009`');
        $this->addSql('ALTER TABLE `game_review` DROP FOREIGN KEY `FK_D6514C34FE673D3E`');
        $this->addSql('ALTER TABLE `game_review` DROP FOREIGN KEY `FK_18AFBE2370478435`');
        $this->addSql('DROP TABLE `game_review`');
    }
}

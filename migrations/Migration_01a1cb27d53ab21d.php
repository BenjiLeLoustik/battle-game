<?php

declare(strict_types=1);

namespace Migrations;

use NeoPHP\Package\Orm\Contract\AbstractMigration;

class Migration_01a1cb27d53ab21d extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(): void
    {
        $this->abortIf($this->getPlatformName() !== 'mysql', 'This migration was generated for mysql.');

        $this->addSql('CREATE TABLE `badge` (`code` VARCHAR(120) NOT NULL, `name` VARCHAR(80) NOT NULL, `description` VARCHAR(160) NOT NULL, `rule` VARCHAR(160) NOT NULL, `params` JSON NOT NULL, `design` JSON NOT NULL, `active` TINYINT(1) NOT NULL, `secret` TINYINT(1) NOT NULL, `position` INT NOT NULL, `id` INT NOT NULL AUTO_INCREMENT, `created_at` DATETIME DEFAULT NULL, `updated_at` DATETIME DEFAULT NULL, `game_id` INT DEFAULT NULL, PRIMARY KEY (`id`)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE UNIQUE INDEX `UNIQ_019FADEF7BEFA2B4` ON `badge` (`code`)');
        $this->addSql('CREATE INDEX `IDX_F85E76160FB0B139` ON `badge` (`game_id`)');
        $this->addSql('CREATE TABLE `user_badge` (`id` INT NOT NULL AUTO_INCREMENT, `created_at` DATETIME DEFAULT NULL, `updated_at` DATETIME DEFAULT NULL, `user_id` INT NOT NULL, `badge_id` INT NOT NULL, PRIMARY KEY (`id`)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE INDEX `IDX_4048C89ED5411B0A` ON `user_badge` (`user_id`)');
        $this->addSql('CREATE INDEX `IDX_5048D01ABB5E52EF` ON `user_badge` (`badge_id`)');
        $this->addSql('ALTER TABLE `game_match` ADD `rewards` JSON DEFAULT NULL');
        $this->addSql("UPDATE `game_match` SET `rewards` = '{}'");
        $this->addSql('ALTER TABLE `game_match` MODIFY `rewards` JSON NOT NULL');
        $this->addSql('ALTER TABLE `user` ADD `featured_badge_id` INT DEFAULT NULL');
        $this->addSql('CREATE INDEX `IDX_8E8C7725CB891690` ON `user` (`featured_badge_id`)');
        $this->addSql('ALTER TABLE `badge` ADD CONSTRAINT `FK_F85E76160FB0B139` FOREIGN KEY (`game_id`) REFERENCES `game` (`id`) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE `user_badge` ADD CONSTRAINT `FK_4048C89ED5411B0A` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE `user_badge` ADD CONSTRAINT `FK_5048D01ABB5E52EF` FOREIGN KEY (`badge_id`) REFERENCES `badge` (`id`) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE `user` ADD CONSTRAINT `FK_8E8C7725CB891690` FOREIGN KEY (`featured_badge_id`) REFERENCES `badge` (`id`) ON DELETE SET NULL');
    }

    public function down(): void
    {
        $this->abortIf($this->getPlatformName() !== 'mysql', 'This migration was generated for mysql.');

        $this->addSql('ALTER TABLE `user` DROP FOREIGN KEY `FK_8E8C7725CB891690`');
        $this->addSql('ALTER TABLE `badge` DROP FOREIGN KEY `FK_F85E76160FB0B139`');
        $this->addSql('ALTER TABLE `user_badge` DROP FOREIGN KEY `FK_4048C89ED5411B0A`');
        $this->addSql('ALTER TABLE `user_badge` DROP FOREIGN KEY `FK_5048D01ABB5E52EF`');
        $this->addSql('DROP TABLE `badge`');
        $this->addSql('DROP TABLE `user_badge`');
        $this->addSql('ALTER TABLE `game_match` DROP `rewards`');
        $this->addSql('DROP INDEX `IDX_8E8C7725CB891690` ON `user`');
        $this->addSql('ALTER TABLE `user` DROP `featured_badge_id`');
    }
}

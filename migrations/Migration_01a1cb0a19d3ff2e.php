<?php

declare(strict_types=1);

namespace Migrations;

use NeoPHP\Package\Orm\Contract\AbstractMigration;

class Migration_01a1cb0a19d3ff2e extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(): void
    {
        $this->abortIf($this->getPlatformName() !== 'mysql', 'This migration was generated for mysql.');

        $this->addSql('ALTER TABLE `user` ADD `bio` VARCHAR(160) DEFAULT NULL');
        $this->addSql('ALTER TABLE `user` ADD `country` VARCHAR(2) DEFAULT NULL');
        $this->addSql('ALTER TABLE `user` ADD `discord_avatar` VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE `user` ADD `preferences` JSON DEFAULT NULL');
        $this->addSql("UPDATE `user` SET `preferences` = '{}'");
        $this->addSql('ALTER TABLE `user` MODIFY `preferences` JSON NOT NULL');
        $this->addSql('ALTER TABLE `user` ADD `pending_email` VARCHAR(180) DEFAULT NULL');
        $this->addSql('ALTER TABLE `user` ADD `email_token` VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE `user` ADD `username_changed_at` DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE `user` ADD `password_changed_at` DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE `user` ADD `deletion_requested_at` DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE `user` ADD `favorite_game_id` INT DEFAULT NULL');
        $this->addSql('CREATE INDEX `IDX_F9F63707649ECD11` ON `user` (`favorite_game_id`)');
        $this->addSql('ALTER TABLE `user` ADD CONSTRAINT `FK_F9F63707649ECD11` FOREIGN KEY (`favorite_game_id`) REFERENCES `game` (`id`) ON DELETE SET NULL');
    }

    public function down(): void
    {
        $this->abortIf($this->getPlatformName() !== 'mysql', 'This migration was generated for mysql.');

        $this->addSql('ALTER TABLE `user` DROP FOREIGN KEY `FK_F9F63707649ECD11`');
        $this->addSql('DROP INDEX `IDX_F9F63707649ECD11` ON `user`');
        $this->addSql('ALTER TABLE `user` DROP `bio`');
        $this->addSql('ALTER TABLE `user` DROP `country`');
        $this->addSql('ALTER TABLE `user` DROP `discord_avatar`');
        $this->addSql('ALTER TABLE `user` DROP `preferences`');
        $this->addSql('ALTER TABLE `user` DROP `pending_email`');
        $this->addSql('ALTER TABLE `user` DROP `email_token`');
        $this->addSql('ALTER TABLE `user` DROP `username_changed_at`');
        $this->addSql('ALTER TABLE `user` DROP `password_changed_at`');
        $this->addSql('ALTER TABLE `user` DROP `deletion_requested_at`');
        $this->addSql('ALTER TABLE `user` DROP `favorite_game_id`');
    }
}

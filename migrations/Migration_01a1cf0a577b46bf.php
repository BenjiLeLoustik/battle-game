<?php

declare(strict_types=1);

namespace Migrations;

use NeoPHP\Package\Orm\Contract\AbstractMigration;

class Migration_01a1cf0a577b46bf extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(): void
    {
        $this->abortIf($this->getPlatformName() !== 'mysql', 'This migration was generated for mysql.');

        $this->addSql('CREATE TABLE `email_campaign` (`subject` VARCHAR(150) NOT NULL, `content` LONGTEXT NOT NULL, `button_label` VARCHAR(60) NOT NULL, `button_url` VARCHAR(255) NOT NULL, `sent_at` DATETIME DEFAULT NULL, `recipients` INT NOT NULL, `id` INT NOT NULL AUTO_INCREMENT, `created_at` DATETIME DEFAULT NULL, `updated_at` DATETIME DEFAULT NULL, `list_id` INT DEFAULT NULL, `author_id` INT DEFAULT NULL, PRIMARY KEY (`id`)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE INDEX `IDX_F317A94DFAD2FBE5` ON `email_campaign` (`list_id`)');
        $this->addSql('CREATE INDEX `IDX_58AB53521E5FC3AD` ON `email_campaign` (`author_id`)');
        $this->addSql('CREATE TABLE `mailing_list` (`name` VARCHAR(80) NOT NULL, `description` VARCHAR(160) NOT NULL, `audience` VARCHAR(20) NOT NULL, `country` VARCHAR(2) DEFAULT NULL, `opt_in` TINYINT(1) NOT NULL, `id` INT NOT NULL AUTO_INCREMENT, `created_at` DATETIME DEFAULT NULL, `updated_at` DATETIME DEFAULT NULL, `game_id` INT DEFAULT NULL, PRIMARY KEY (`id`)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE INDEX `IDX_93B2569B8DA614CC` ON `mailing_list` (`game_id`)');
        $this->addSql('ALTER TABLE `email_campaign` ADD CONSTRAINT `FK_F317A94DFAD2FBE5` FOREIGN KEY (`list_id`) REFERENCES `mailing_list` (`id`) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE `email_campaign` ADD CONSTRAINT `FK_58AB53521E5FC3AD` FOREIGN KEY (`author_id`) REFERENCES `user` (`id`) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE `mailing_list` ADD CONSTRAINT `FK_93B2569B8DA614CC` FOREIGN KEY (`game_id`) REFERENCES `game` (`id`) ON DELETE SET NULL');
    }

    public function down(): void
    {
        $this->abortIf($this->getPlatformName() !== 'mysql', 'This migration was generated for mysql.');

        $this->addSql('ALTER TABLE `email_campaign` DROP FOREIGN KEY `FK_F317A94DFAD2FBE5`');
        $this->addSql('ALTER TABLE `email_campaign` DROP FOREIGN KEY `FK_58AB53521E5FC3AD`');
        $this->addSql('ALTER TABLE `mailing_list` DROP FOREIGN KEY `FK_93B2569B8DA614CC`');
        $this->addSql('DROP TABLE `email_campaign`');
        $this->addSql('DROP TABLE `mailing_list`');
    }
}

<?php

declare(strict_types=1);

namespace Migrations;

use NeoPHP\Package\Orm\Contract\AbstractMigration;

class Migration_01a1cb315409d39f extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(): void
    {
        $this->abortIf($this->getPlatformName() !== 'mysql', 'This migration was generated for mysql.');

        $this->addSql('CREATE TABLE `admin_role` (`name` VARCHAR(60) NOT NULL, `color` VARCHAR(7) NOT NULL, `description` VARCHAR(160) NOT NULL, `permissions` JSON NOT NULL, `system` TINYINT(1) NOT NULL, `id` INT NOT NULL AUTO_INCREMENT, `created_at` DATETIME DEFAULT NULL, `updated_at` DATETIME DEFAULT NULL, PRIMARY KEY (`id`)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE `audit_log` (`action` VARCHAR(80) NOT NULL, `detail` VARCHAR(255) NOT NULL, `id` INT NOT NULL AUTO_INCREMENT, `created_at` DATETIME DEFAULT NULL, `updated_at` DATETIME DEFAULT NULL, `user_id` INT DEFAULT NULL, PRIMARY KEY (`id`)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE INDEX `IDX_30B4175DCE18F155` ON `audit_log` (`user_id`)');
        $this->addSql('CREATE TABLE `banner` (`text` VARCHAR(160) NOT NULL, `cta` VARCHAR(40) DEFAULT NULL, `url` VARCHAR(255) DEFAULT NULL, `type` VARCHAR(20) NOT NULL, `audience` VARCHAR(20) NOT NULL, `pages` VARCHAR(20) NOT NULL, `starts_at` DATETIME DEFAULT NULL, `ends_at` DATETIME DEFAULT NULL, `dismissible` TINYINT(1) NOT NULL, `active` TINYINT(1) NOT NULL, `position` INT NOT NULL, `id` INT NOT NULL AUTO_INCREMENT, `created_at` DATETIME DEFAULT NULL, `updated_at` DATETIME DEFAULT NULL, `list_id` INT NOT NULL, PRIMARY KEY (`id`)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE INDEX `IDX_C9CCCC1FF8F10514` ON `banner` (`list_id`)');
        $this->addSql('CREATE TABLE `banner_list` (`name` VARCHAR(60) NOT NULL, `live` TINYINT(1) NOT NULL, `id` INT NOT NULL AUTO_INCREMENT, `created_at` DATETIME DEFAULT NULL, `updated_at` DATETIME DEFAULT NULL, PRIMARY KEY (`id`)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE `category` (`name` VARCHAR(60) NOT NULL, `slug` VARCHAR(60) NOT NULL, `color` VARCHAR(7) NOT NULL, `position` INT NOT NULL, `active` TINYINT(1) NOT NULL, `id` INT NOT NULL AUTO_INCREMENT, `created_at` DATETIME DEFAULT NULL, `updated_at` DATETIME DEFAULT NULL, PRIMARY KEY (`id`)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE UNIQUE INDEX `UNIQ_5D60D114D0DD9940` ON `category` (`slug`)');
        $this->addSql('CREATE TABLE `sanction` (`type` VARCHAR(20) NOT NULL, `reason` VARCHAR(20) NOT NULL, `message` VARCHAR(500) DEFAULT NULL, `ends_at` DATETIME DEFAULT NULL, `revoked_at` DATETIME DEFAULT NULL, `id` INT NOT NULL AUTO_INCREMENT, `created_at` DATETIME DEFAULT NULL, `updated_at` DATETIME DEFAULT NULL, `user_id` INT NOT NULL, `author_id` INT DEFAULT NULL, PRIMARY KEY (`id`)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE INDEX `IDX_68B7F63530670721` ON `sanction` (`user_id`)');
        $this->addSql('CREATE INDEX `IDX_DB7949734C459A4B` ON `sanction` (`author_id`)');
        $this->addSql('CREATE TABLE `setting` (`name` VARCHAR(80) NOT NULL, `value` JSON NOT NULL, `id` INT NOT NULL AUTO_INCREMENT, `created_at` DATETIME DEFAULT NULL, `updated_at` DATETIME DEFAULT NULL, PRIMARY KEY (`id`)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE UNIQUE INDEX `UNIQ_D2763EFE78E9B292` ON `setting` (`name`)');
        $this->addSql('ALTER TABLE `game` ADD `repository` VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE `game` ADD `reference` VARCHAR(80) DEFAULT NULL');
        $this->addSql('ALTER TABLE `game` ADD `category_id` INT DEFAULT NULL');
        $this->addSql('CREATE INDEX `IDX_296136135989A071` ON `game` (`category_id`)');
        $this->addSql("INSERT INTO `category` (`name`, `slug`, `color`, `position`, `active`, `created_at`, `updated_at`) VALUES ('Réflexe', 'reflex', '#2EE6D6', 0, 1, NOW(), NOW()), ('Cerveau', 'brain', '#A78BFA', 1, 1, NOW(), NOW()), ('Mémoire', 'memory', '#FF3DCB', 2, 1, NOW(), NOW()), ('Observation', 'observation', '#4ADE80', 3, 1, NOW(), NOW()), ('Logique', 'logic', '#FFC94A', 4, 1, NOW(), NOW())");
        $this->addSql('UPDATE `game` g JOIN `category` c ON c.slug = g.category SET g.category_id = c.id');
        $this->addSql('ALTER TABLE `game` DROP `category`');
        $this->addSql('ALTER TABLE `room` ADD `official` TINYINT(1) NOT NULL');
        $this->addSql('ALTER TABLE `user` ADD `admin_note` VARCHAR(1000) DEFAULT NULL');
        $this->addSql('ALTER TABLE `user` ADD `admin_role_id` INT DEFAULT NULL');
        $this->addSql('CREATE INDEX `IDX_3FC1BD09BABCE75A` ON `user` (`admin_role_id`)');
        $this->addSql("INSERT INTO `admin_role` (`name`, `color`, `description`, `permissions`, `system`, `created_at`, `updated_at`) VALUES ('Administrateur', '#B7AEFF', 'Tous les droits. Ne peut pas être supprimé.', '[]', 1, NOW(), NOW()), ('Modérateur', '#93C5FD', 'Utilisateurs, rooms, modération.', '[\"dash.view\", \"users.view\", \"users.edit\", \"users.suspend\", \"rooms.view\", \"rooms.close\", \"mod.view\", \"mod.sanction\", \"mod.words\"]', 0, NOW(), NOW()), ('Éditeur de jeux', '#6EE7A0', 'Atelier, jeux, traductions des contenus.', '[\"dash.view\", \"games.install\", \"games.state\", \"studio.view\", \"studio.create\", \"studio.edit\", \"studio.publish\", \"studio.team\", \"sys.langs\"]', 0, NOW(), NOW()), ('Support', '#FFB877', 'Lecture des comptes et des rooms.', '[\"dash.view\", \"users.view\", \"rooms.view\", \"mod.view\"]', 0, NOW(), NOW())");
        $this->addSql('UPDATE `user` SET admin_role_id = (SELECT id FROM `admin_role` WHERE `system` = 1 LIMIT 1) WHERE JSON_CONTAINS(roles, \'"ROLE_ADMIN"\')');
        $this->addSql("INSERT INTO `banner_list` (`name`, `live`, `created_at`, `updated_at`) VALUES ('Par défaut', 1, NOW(), NOW())");
        $this->addSql('ALTER TABLE `audit_log` ADD CONSTRAINT `FK_30B4175DCE18F155` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE `banner` ADD CONSTRAINT `FK_C9CCCC1FF8F10514` FOREIGN KEY (`list_id`) REFERENCES `banner_list` (`id`) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE `sanction` ADD CONSTRAINT `FK_68B7F63530670721` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE `sanction` ADD CONSTRAINT `FK_DB7949734C459A4B` FOREIGN KEY (`author_id`) REFERENCES `user` (`id`) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE `game` ADD CONSTRAINT `FK_296136135989A071` FOREIGN KEY (`category_id`) REFERENCES `category` (`id`)');
        $this->addSql('ALTER TABLE `user` ADD CONSTRAINT `FK_3FC1BD09BABCE75A` FOREIGN KEY (`admin_role_id`) REFERENCES `admin_role` (`id`) ON DELETE SET NULL');
    }

    public function down(): void
    {
        $this->abortIf($this->getPlatformName() !== 'mysql', 'This migration was generated for mysql.');

        $this->addSql('ALTER TABLE `game` DROP FOREIGN KEY `FK_296136135989A071`');
        $this->addSql('ALTER TABLE `user` DROP FOREIGN KEY `FK_3FC1BD09BABCE75A`');
        $this->addSql('ALTER TABLE `audit_log` DROP FOREIGN KEY `FK_30B4175DCE18F155`');
        $this->addSql('ALTER TABLE `banner` DROP FOREIGN KEY `FK_C9CCCC1FF8F10514`');
        $this->addSql('ALTER TABLE `sanction` DROP FOREIGN KEY `FK_68B7F63530670721`');
        $this->addSql('ALTER TABLE `sanction` DROP FOREIGN KEY `FK_DB7949734C459A4B`');
        $this->addSql('DROP TABLE `admin_role`');
        $this->addSql('DROP TABLE `audit_log`');
        $this->addSql('DROP TABLE `banner`');
        $this->addSql('DROP TABLE `banner_list`');
        $this->addSql('DROP TABLE `category`');
        $this->addSql('DROP TABLE `sanction`');
        $this->addSql('DROP TABLE `setting`');
        $this->addSql('ALTER TABLE `game` ADD `category` VARCHAR(50) NOT NULL');
        $this->addSql('DROP INDEX `IDX_296136135989A071` ON `game`');
        $this->addSql('ALTER TABLE `game` DROP `repository`');
        $this->addSql('ALTER TABLE `game` DROP `reference`');
        $this->addSql('ALTER TABLE `game` DROP `category_id`');
        $this->addSql('ALTER TABLE `room` DROP `official`');
        $this->addSql('DROP INDEX `IDX_3FC1BD09BABCE75A` ON `user`');
        $this->addSql('ALTER TABLE `user` DROP `admin_note`');
        $this->addSql('ALTER TABLE `user` DROP `admin_role_id`');
    }
}

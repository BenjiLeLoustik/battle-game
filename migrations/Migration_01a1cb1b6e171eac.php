<?php

declare(strict_types=1);

namespace Migrations;

use NeoPHP\Package\Orm\Contract\AbstractMigration;

class Migration_01a1cb1b6e171eac extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(): void
    {
        $this->abortIf($this->getPlatformName() !== 'mysql', 'This migration was generated for mysql.');

        $this->addSql('CREATE TABLE `chat_message` (`channel` VARCHAR(40) NOT NULL, `text` VARCHAR(1000) NOT NULL, `id` INT NOT NULL AUTO_INCREMENT, `created_at` DATETIME DEFAULT NULL, `updated_at` DATETIME DEFAULT NULL, `user_id` INT DEFAULT NULL, `room_id` INT DEFAULT NULL, PRIMARY KEY (`id`)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE INDEX `IDX_B0D800989F72EEC7` ON `chat_message` (`user_id`)');
        $this->addSql('CREATE INDEX `IDX_ACC49F92B498ED1C` ON `chat_message` (`room_id`)');
        $this->addSql('CREATE TABLE `chat_read` (`channel` VARCHAR(40) NOT NULL, `last_id` INT NOT NULL, `id` INT NOT NULL AUTO_INCREMENT, `created_at` DATETIME DEFAULT NULL, `updated_at` DATETIME DEFAULT NULL, `user_id` INT NOT NULL, PRIMARY KEY (`id`)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE INDEX `IDX_7C97B2C42D13A295` ON `chat_read` (`user_id`)');
        $this->addSql('CREATE TABLE `friendship` (`status` VARCHAR(20) NOT NULL, `id` INT NOT NULL AUTO_INCREMENT, `created_at` DATETIME DEFAULT NULL, `updated_at` DATETIME DEFAULT NULL, `requester_id` INT NOT NULL, `addressee_id` INT NOT NULL, PRIMARY KEY (`id`)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE INDEX `IDX_7CB377DD2A86DE45` ON `friendship` (`requester_id`)');
        $this->addSql('CREATE INDEX `IDX_D5CE9972D2BC3E6E` ON `friendship` (`addressee_id`)');
        $this->addSql('CREATE TABLE `notification` (`type` VARCHAR(30) NOT NULL, `data` JSON NOT NULL, `read_at` DATETIME DEFAULT NULL, `id` INT NOT NULL AUTO_INCREMENT, `created_at` DATETIME DEFAULT NULL, `updated_at` DATETIME DEFAULT NULL, `user_id` INT NOT NULL, PRIMARY KEY (`id`)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE INDEX `IDX_9FFB7F87BDF99FDC` ON `notification` (`user_id`)');
        $this->addSql('CREATE TABLE `report` (`reason` VARCHAR(20) NOT NULL, `comment` VARCHAR(500) DEFAULT NULL, `status` VARCHAR(20) NOT NULL, `id` INT NOT NULL AUTO_INCREMENT, `created_at` DATETIME DEFAULT NULL, `updated_at` DATETIME DEFAULT NULL, `reporter_id` INT DEFAULT NULL, `reported_id` INT NOT NULL, PRIMARY KEY (`id`)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE INDEX `IDX_378909153FDD42CB` ON `report` (`reporter_id`)');
        $this->addSql('CREATE INDEX `IDX_1640B87A10376F79` ON `report` (`reported_id`)');
        $this->addSql('CREATE TABLE `user_block` (`id` INT NOT NULL AUTO_INCREMENT, `created_at` DATETIME DEFAULT NULL, `updated_at` DATETIME DEFAULT NULL, `blocker_id` INT NOT NULL, `blocked_id` INT NOT NULL, PRIMARY KEY (`id`)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE INDEX `IDX_FBF30919EE3E966A` ON `user_block` (`blocker_id`)');
        $this->addSql('CREATE INDEX `IDX_8598E64948AA5D57` ON `user_block` (`blocked_id`)');
        $this->addSql('ALTER TABLE `user` ADD `seen_at` DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE `chat_message` ADD CONSTRAINT `FK_B0D800989F72EEC7` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE `chat_message` ADD CONSTRAINT `FK_ACC49F92B498ED1C` FOREIGN KEY (`room_id`) REFERENCES `room` (`id`) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE `chat_read` ADD CONSTRAINT `FK_7C97B2C42D13A295` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE `friendship` ADD CONSTRAINT `FK_7CB377DD2A86DE45` FOREIGN KEY (`requester_id`) REFERENCES `user` (`id`) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE `friendship` ADD CONSTRAINT `FK_D5CE9972D2BC3E6E` FOREIGN KEY (`addressee_id`) REFERENCES `user` (`id`) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE `notification` ADD CONSTRAINT `FK_9FFB7F87BDF99FDC` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE `report` ADD CONSTRAINT `FK_378909153FDD42CB` FOREIGN KEY (`reporter_id`) REFERENCES `user` (`id`) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE `report` ADD CONSTRAINT `FK_1640B87A10376F79` FOREIGN KEY (`reported_id`) REFERENCES `user` (`id`) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE `user_block` ADD CONSTRAINT `FK_FBF30919EE3E966A` FOREIGN KEY (`blocker_id`) REFERENCES `user` (`id`) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE `user_block` ADD CONSTRAINT `FK_8598E64948AA5D57` FOREIGN KEY (`blocked_id`) REFERENCES `user` (`id`) ON DELETE CASCADE');
    }

    public function down(): void
    {
        $this->abortIf($this->getPlatformName() !== 'mysql', 'This migration was generated for mysql.');

        $this->addSql('ALTER TABLE `chat_message` DROP FOREIGN KEY `FK_B0D800989F72EEC7`');
        $this->addSql('ALTER TABLE `chat_message` DROP FOREIGN KEY `FK_ACC49F92B498ED1C`');
        $this->addSql('ALTER TABLE `chat_read` DROP FOREIGN KEY `FK_7C97B2C42D13A295`');
        $this->addSql('ALTER TABLE `friendship` DROP FOREIGN KEY `FK_7CB377DD2A86DE45`');
        $this->addSql('ALTER TABLE `friendship` DROP FOREIGN KEY `FK_D5CE9972D2BC3E6E`');
        $this->addSql('ALTER TABLE `notification` DROP FOREIGN KEY `FK_9FFB7F87BDF99FDC`');
        $this->addSql('ALTER TABLE `report` DROP FOREIGN KEY `FK_378909153FDD42CB`');
        $this->addSql('ALTER TABLE `report` DROP FOREIGN KEY `FK_1640B87A10376F79`');
        $this->addSql('ALTER TABLE `user_block` DROP FOREIGN KEY `FK_FBF30919EE3E966A`');
        $this->addSql('ALTER TABLE `user_block` DROP FOREIGN KEY `FK_8598E64948AA5D57`');
        $this->addSql('DROP TABLE `chat_message`');
        $this->addSql('DROP TABLE `chat_read`');
        $this->addSql('DROP TABLE `friendship`');
        $this->addSql('DROP TABLE `notification`');
        $this->addSql('DROP TABLE `report`');
        $this->addSql('DROP TABLE `user_block`');
        $this->addSql('ALTER TABLE `user` DROP `seen_at`');
    }
}

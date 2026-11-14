<?php

declare(strict_types=1);

namespace Migrations;

use NeoPHP\Package\Orm\Contract\AbstractMigration;

class Migration_01a1cafb4d5ce9cf extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(): void
    {
        $this->abortIf($this->getPlatformName() !== 'mysql', 'This migration was generated for mysql.');

        $this->addSql('CREATE TABLE `room` (`name` VARCHAR(60) NOT NULL, `code` VARCHAR(7) NOT NULL, `visibility` VARCHAR(20) NOT NULL, `password` VARCHAR(60) DEFAULT NULL, `max_players` INT NOT NULL, `choices` INT NOT NULL, `vote_duration` INT NOT NULL, `guests` TINYINT(1) NOT NULL, `spectators` TINYINT(1) NOT NULL, `permanent` TINYINT(1) NOT NULL, `status` VARCHAR(20) NOT NULL, `round` JSON NOT NULL, `history` JSON NOT NULL, `match_code` VARCHAR(8) DEFAULT NULL, `id` INT NOT NULL AUTO_INCREMENT, `created_at` DATETIME DEFAULT NULL, `updated_at` DATETIME DEFAULT NULL, `host_id` INT DEFAULT NULL, PRIMARY KEY (`id`)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE UNIQUE INDEX `UNIQ_E701AD31EC92088B` ON `room` (`code`)');
        $this->addSql('CREATE INDEX `IDX_8FFD5BF0B868B513` ON `room` (`host_id`)');
        $this->addSql('CREATE TABLE `room_member` (`spectator` TINYINT(1) NOT NULL, `seen_at` BIGINT NOT NULL, `id` INT NOT NULL AUTO_INCREMENT, `created_at` DATETIME DEFAULT NULL, `updated_at` DATETIME DEFAULT NULL, `room_id` INT NOT NULL, `user_id` INT NOT NULL, PRIMARY KEY (`id`)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE INDEX `IDX_AC1408BC057D6EF8` ON `room_member` (`room_id`)');
        $this->addSql('CREATE INDEX `IDX_9EB275E8ED4924D7` ON `room_member` (`user_id`)');
        $this->addSql('CREATE TABLE `room_message` (`text` VARCHAR(500) NOT NULL, `id` INT NOT NULL AUTO_INCREMENT, `created_at` DATETIME DEFAULT NULL, `updated_at` DATETIME DEFAULT NULL, `room_id` INT NOT NULL, `user_id` INT DEFAULT NULL, PRIMARY KEY (`id`)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE INDEX `IDX_1878D0CB396221C6` ON `room_message` (`room_id`)');
        $this->addSql('CREATE INDEX `IDX_D4C833ABCA8D8831` ON `room_message` (`user_id`)');
        $this->addSql('ALTER TABLE `game_match` ADD `room_id` INT DEFAULT NULL');
        $this->addSql('CREATE INDEX `IDX_D957F7C6E47F10BF` ON `game_match` (`room_id`)');
        $this->addSql('ALTER TABLE `room` ADD CONSTRAINT `FK_8FFD5BF0B868B513` FOREIGN KEY (`host_id`) REFERENCES `user` (`id`) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE `room_member` ADD CONSTRAINT `FK_AC1408BC057D6EF8` FOREIGN KEY (`room_id`) REFERENCES `room` (`id`) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE `room_member` ADD CONSTRAINT `FK_9EB275E8ED4924D7` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE `room_message` ADD CONSTRAINT `FK_1878D0CB396221C6` FOREIGN KEY (`room_id`) REFERENCES `room` (`id`) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE `room_message` ADD CONSTRAINT `FK_D4C833ABCA8D8831` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE `game_match` ADD CONSTRAINT `FK_D957F7C6E47F10BF` FOREIGN KEY (`room_id`) REFERENCES `room` (`id`) ON DELETE SET NULL');
    }

    public function down(): void
    {
        $this->abortIf($this->getPlatformName() !== 'mysql', 'This migration was generated for mysql.');

        $this->addSql('ALTER TABLE `game_match` DROP FOREIGN KEY `FK_D957F7C6E47F10BF`');
        $this->addSql('ALTER TABLE `room` DROP FOREIGN KEY `FK_8FFD5BF0B868B513`');
        $this->addSql('ALTER TABLE `room_member` DROP FOREIGN KEY `FK_AC1408BC057D6EF8`');
        $this->addSql('ALTER TABLE `room_member` DROP FOREIGN KEY `FK_9EB275E8ED4924D7`');
        $this->addSql('ALTER TABLE `room_message` DROP FOREIGN KEY `FK_1878D0CB396221C6`');
        $this->addSql('ALTER TABLE `room_message` DROP FOREIGN KEY `FK_D4C833ABCA8D8831`');
        $this->addSql('DROP TABLE `room`');
        $this->addSql('DROP TABLE `room_member`');
        $this->addSql('DROP TABLE `room_message`');
        $this->addSql('DROP INDEX `IDX_D957F7C6E47F10BF` ON `game_match`');
        $this->addSql('ALTER TABLE `game_match` DROP `room_id`');
    }
}

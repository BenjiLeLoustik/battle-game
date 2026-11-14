<?php

declare(strict_types=1);

namespace Migrations;

use NeoPHP\Package\Orm\Contract\AbstractMigration;

class Migration_01a1d90c82f60c4d extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(): void
    {
        $this->abortIf($this->getPlatformName() !== 'mysql', 'This migration was generated for mysql.');

        $this->addSql('CREATE TABLE `clan` (`name` VARCHAR(40) NOT NULL, `tag` VARCHAR(5) NOT NULL, `description` VARCHAR(300) NOT NULL, `color` VARCHAR(7) NOT NULL, `open` TINYINT(1) NOT NULL, `id` INT NOT NULL AUTO_INCREMENT, `created_at` DATETIME DEFAULT NULL, `updated_at` DATETIME DEFAULT NULL, PRIMARY KEY (`id`)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE UNIQUE INDEX `UNIQ_88618EABA678154E` ON `clan` (`name`)');
        $this->addSql('CREATE UNIQUE INDEX `UNIQ_60167CCE9A6953C0` ON `clan` (`tag`)');
        $this->addSql('CREATE TABLE `clan_member` (`role` VARCHAR(10) NOT NULL, `id` INT NOT NULL AUTO_INCREMENT, `created_at` DATETIME DEFAULT NULL, `updated_at` DATETIME DEFAULT NULL, `clan_id` INT NOT NULL, `user_id` INT NOT NULL, PRIMARY KEY (`id`)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE INDEX `IDX_6EB5BDA5BD55B7AA` ON `clan_member` (`clan_id`)');
        $this->addSql('CREATE INDEX `IDX_D35558AB7DE1E410` ON `clan_member` (`user_id`)');
        $this->addSql('CREATE TABLE `clan_request` (`id` INT NOT NULL AUTO_INCREMENT, `created_at` DATETIME DEFAULT NULL, `updated_at` DATETIME DEFAULT NULL, `clan_id` INT NOT NULL, `user_id` INT NOT NULL, PRIMARY KEY (`id`)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE INDEX `IDX_A67BED074D71421E` ON `clan_request` (`clan_id`)');
        $this->addSql('CREATE INDEX `IDX_A4A0B30C0FCAD74C` ON `clan_request` (`user_id`)');
        $this->addSql('CREATE TABLE `game_event` (`name` VARCHAR(80) NOT NULL, `description` VARCHAR(300) NOT NULL, `starts_at` DATETIME NOT NULL, `ends_at` DATETIME NOT NULL, `xp_multiplier` DOUBLE PRECISION NOT NULL, `coins_multiplier` DOUBLE PRECISION NOT NULL, `color` VARCHAR(7) NOT NULL, `active` TINYINT(1) NOT NULL, `id` INT NOT NULL AUTO_INCREMENT, `created_at` DATETIME DEFAULT NULL, `updated_at` DATETIME DEFAULT NULL, `game_id` INT DEFAULT NULL, PRIMARY KEY (`id`)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE INDEX `IDX_565F0739DA169544` ON `game_event` (`game_id`)');
        $this->addSql('CREATE TABLE `season_pass` (`xp` INT NOT NULL, `claimed` JSON NOT NULL, `id` INT NOT NULL AUTO_INCREMENT, `created_at` DATETIME DEFAULT NULL, `updated_at` DATETIME DEFAULT NULL, `user_id` INT NOT NULL, `season_id` INT NOT NULL, PRIMARY KEY (`id`)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE INDEX `IDX_B6D285DC170BABD0` ON `season_pass` (`user_id`)');
        $this->addSql('CREATE INDEX `IDX_C21420F70D0AD911` ON `season_pass` (`season_id`)');
        $this->addSql('CREATE TABLE `tournament` (`name` VARCHAR(80) NOT NULL, `description` LONGTEXT NOT NULL, `starts_at` DATETIME NOT NULL, `ends_at` DATETIME NOT NULL, `max_players` INT DEFAULT NULL, `prizes` VARCHAR(200) NOT NULL, `published` TINYINT(1) NOT NULL, `rewarded_at` DATETIME DEFAULT NULL, `id` INT NOT NULL AUTO_INCREMENT, `created_at` DATETIME DEFAULT NULL, `updated_at` DATETIME DEFAULT NULL, `game_id` INT NOT NULL, PRIMARY KEY (`id`)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE INDEX `IDX_823F967160B7D3C2` ON `tournament` (`game_id`)');
        $this->addSql('CREATE TABLE `tournament_entry` (`id` INT NOT NULL AUTO_INCREMENT, `created_at` DATETIME DEFAULT NULL, `updated_at` DATETIME DEFAULT NULL, `tournament_id` INT NOT NULL, `user_id` INT NOT NULL, PRIMARY KEY (`id`)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE INDEX `IDX_37878F554DF8BD07` ON `tournament_entry` (`tournament_id`)');
        $this->addSql('CREATE INDEX `IDX_F4D41A1C90F164E5` ON `tournament_entry` (`user_id`)');
        $this->addSql('CREATE TABLE `user_rating` (`rating` INT NOT NULL, `peak` INT NOT NULL, `games` INT NOT NULL, `wins` INT NOT NULL, `id` INT NOT NULL AUTO_INCREMENT, `created_at` DATETIME DEFAULT NULL, `updated_at` DATETIME DEFAULT NULL, `user_id` INT NOT NULL, `game_id` INT NOT NULL, PRIMARY KEY (`id`)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE INDEX `IDX_AEFDF23D0A128C37` ON `user_rating` (`user_id`)');
        $this->addSql('CREATE INDEX `IDX_E04FF899D5604FA1` ON `user_rating` (`game_id`)');
        $this->addSql('ALTER TABLE `user` ADD `referral_rewarded_at` DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE `user` ADD `showcase` JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE `user` ADD `referred_by_id` INT DEFAULT NULL');
        $this->addSql('CREATE INDEX `IDX_DF9ED6282BB7AD2A` ON `user` (`referred_by_id`)');
        $this->addSql('ALTER TABLE `clan_member` ADD CONSTRAINT `FK_6EB5BDA5BD55B7AA` FOREIGN KEY (`clan_id`) REFERENCES `clan` (`id`) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE `clan_member` ADD CONSTRAINT `FK_D35558AB7DE1E410` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE `clan_request` ADD CONSTRAINT `FK_A67BED074D71421E` FOREIGN KEY (`clan_id`) REFERENCES `clan` (`id`) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE `clan_request` ADD CONSTRAINT `FK_A4A0B30C0FCAD74C` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE `game_event` ADD CONSTRAINT `FK_565F0739DA169544` FOREIGN KEY (`game_id`) REFERENCES `game` (`id`) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE `season_pass` ADD CONSTRAINT `FK_B6D285DC170BABD0` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE `season_pass` ADD CONSTRAINT `FK_C21420F70D0AD911` FOREIGN KEY (`season_id`) REFERENCES `season` (`id`) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE `tournament` ADD CONSTRAINT `FK_823F967160B7D3C2` FOREIGN KEY (`game_id`) REFERENCES `game` (`id`) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE `tournament_entry` ADD CONSTRAINT `FK_37878F554DF8BD07` FOREIGN KEY (`tournament_id`) REFERENCES `tournament` (`id`) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE `tournament_entry` ADD CONSTRAINT `FK_F4D41A1C90F164E5` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE `user_rating` ADD CONSTRAINT `FK_AEFDF23D0A128C37` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE `user_rating` ADD CONSTRAINT `FK_E04FF899D5604FA1` FOREIGN KEY (`game_id`) REFERENCES `game` (`id`) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE `user` ADD CONSTRAINT `FK_DF9ED6282BB7AD2A` FOREIGN KEY (`referred_by_id`) REFERENCES `user` (`id`) ON DELETE SET NULL');
    }

    public function down(): void
    {
        $this->abortIf($this->getPlatformName() !== 'mysql', 'This migration was generated for mysql.');

        $this->addSql('ALTER TABLE `user` DROP FOREIGN KEY `FK_DF9ED6282BB7AD2A`');
        $this->addSql('ALTER TABLE `clan_member` DROP FOREIGN KEY `FK_6EB5BDA5BD55B7AA`');
        $this->addSql('ALTER TABLE `clan_member` DROP FOREIGN KEY `FK_D35558AB7DE1E410`');
        $this->addSql('ALTER TABLE `clan_request` DROP FOREIGN KEY `FK_A67BED074D71421E`');
        $this->addSql('ALTER TABLE `clan_request` DROP FOREIGN KEY `FK_A4A0B30C0FCAD74C`');
        $this->addSql('ALTER TABLE `game_event` DROP FOREIGN KEY `FK_565F0739DA169544`');
        $this->addSql('ALTER TABLE `season_pass` DROP FOREIGN KEY `FK_B6D285DC170BABD0`');
        $this->addSql('ALTER TABLE `season_pass` DROP FOREIGN KEY `FK_C21420F70D0AD911`');
        $this->addSql('ALTER TABLE `tournament` DROP FOREIGN KEY `FK_823F967160B7D3C2`');
        $this->addSql('ALTER TABLE `tournament_entry` DROP FOREIGN KEY `FK_37878F554DF8BD07`');
        $this->addSql('ALTER TABLE `tournament_entry` DROP FOREIGN KEY `FK_F4D41A1C90F164E5`');
        $this->addSql('ALTER TABLE `user_rating` DROP FOREIGN KEY `FK_AEFDF23D0A128C37`');
        $this->addSql('ALTER TABLE `user_rating` DROP FOREIGN KEY `FK_E04FF899D5604FA1`');
        $this->addSql('DROP TABLE `clan`');
        $this->addSql('DROP TABLE `clan_member`');
        $this->addSql('DROP TABLE `clan_request`');
        $this->addSql('DROP TABLE `game_event`');
        $this->addSql('DROP TABLE `season_pass`');
        $this->addSql('DROP TABLE `tournament`');
        $this->addSql('DROP TABLE `tournament_entry`');
        $this->addSql('DROP TABLE `user_rating`');
        $this->addSql('DROP INDEX `IDX_DF9ED6282BB7AD2A` ON `user`');
        $this->addSql('ALTER TABLE `user` DROP `referral_rewarded_at`');
        $this->addSql('ALTER TABLE `user` DROP `showcase`');
        $this->addSql('ALTER TABLE `user` DROP `referred_by_id`');
    }
}

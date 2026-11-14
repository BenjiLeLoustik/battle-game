<?php

declare(strict_types=1);

namespace Migrations;

use NeoPHP\Package\Orm\Contract\AbstractMigration;

class Migration_01a1d51d3656e2a4 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(): void
    {
        $this->abortIf($this->getPlatformName() !== 'mysql', 'This migration was generated for mysql.');

        $this->addSql('CREATE TABLE `coin_transaction` (`amount` INT NOT NULL, `reason` VARCHAR(40) NOT NULL, `label` VARCHAR(160) NOT NULL, `id` INT NOT NULL AUTO_INCREMENT, `created_at` DATETIME DEFAULT NULL, `updated_at` DATETIME DEFAULT NULL, `user_id` INT NOT NULL, PRIMARY KEY (`id`)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE INDEX `IDX_9446B734E2779B4D` ON `coin_transaction` (`user_id`)');
        $this->addSql('CREATE TABLE `shop_order` (`reference` VARCHAR(16) NOT NULL, `product_name` VARCHAR(80) NOT NULL, `type` VARCHAR(20) NOT NULL, `payment` VARCHAR(10) NOT NULL, `amount_cents` INT DEFAULT NULL, `amount_coins` INT DEFAULT NULL, `status` VARCHAR(20) NOT NULL, `stripe_session` VARCHAR(120) DEFAULT NULL, `shipping` JSON NOT NULL, `tracking` VARCHAR(120) DEFAULT NULL, `note` VARCHAR(500) DEFAULT NULL, `paid_at` DATETIME DEFAULT NULL, `id` INT NOT NULL AUTO_INCREMENT, `created_at` DATETIME DEFAULT NULL, `updated_at` DATETIME DEFAULT NULL, `user_id` INT DEFAULT NULL, `product_id` INT DEFAULT NULL, PRIMARY KEY (`id`)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE UNIQUE INDEX `UNIQ_B57278E30041033E` ON `shop_order` (`reference`)');
        $this->addSql('CREATE INDEX `IDX_F0782E59BB85D9CC` ON `shop_order` (`user_id`)');
        $this->addSql('CREATE INDEX `IDX_AD73E8E01C396049` ON `shop_order` (`product_id`)');
        $this->addSql('CREATE TABLE `shop_product` (`name` VARCHAR(80) NOT NULL, `slug` VARCHAR(100) NOT NULL, `description` LONGTEXT NOT NULL, `type` VARCHAR(20) NOT NULL, `price_cents` INT DEFAULT NULL, `price_coins` INT DEFAULT NULL, `image` VARCHAR(255) DEFAULT NULL, `active` TINYINT(1) NOT NULL, `position` INT NOT NULL, `stock` INT DEFAULT NULL, `payload` JSON NOT NULL, `id` INT NOT NULL AUTO_INCREMENT, `created_at` DATETIME DEFAULT NULL, `updated_at` DATETIME DEFAULT NULL, PRIMARY KEY (`id`)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE UNIQUE INDEX `UNIQ_BD4C7DF19C2D942B` ON `shop_product` (`slug`)');
        $this->addSql('CREATE TABLE `subscription` (`plan` VARCHAR(20) NOT NULL, `status` VARCHAR(20) NOT NULL, `ends_at` DATETIME NOT NULL, `stripe_subscription` VARCHAR(120) DEFAULT NULL, `stripe_customer` VARCHAR(120) DEFAULT NULL, `id` INT NOT NULL AUTO_INCREMENT, `created_at` DATETIME DEFAULT NULL, `updated_at` DATETIME DEFAULT NULL, `user_id` INT NOT NULL, PRIMARY KEY (`id`)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE INDEX `IDX_2DCF445D125D7927` ON `subscription` (`user_id`)');
        $this->addSql('CREATE TABLE `user_item` (`equipped` TINYINT(1) NOT NULL, `id` INT NOT NULL AUTO_INCREMENT, `created_at` DATETIME DEFAULT NULL, `updated_at` DATETIME DEFAULT NULL, `user_id` INT NOT NULL, `product_id` INT NOT NULL, PRIMARY KEY (`id`)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE INDEX `IDX_3EA09CD33AC587B7` ON `user_item` (`user_id`)');
        $this->addSql('CREATE INDEX `IDX_8B7212B5BA74D11A` ON `user_item` (`product_id`)');
        $this->addSql('CREATE TABLE `wallet` (`coins` INT NOT NULL, `lives_used` INT NOT NULL, `lives_day` DATE DEFAULT NULL, `bonus_lives` INT NOT NULL, `id` INT NOT NULL AUTO_INCREMENT, `created_at` DATETIME DEFAULT NULL, `updated_at` DATETIME DEFAULT NULL, `user_id` INT NOT NULL, PRIMARY KEY (`id`)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE INDEX `IDX_F60ED11689F25626` ON `wallet` (`user_id`)');
        $this->addSql('ALTER TABLE `coin_transaction` ADD CONSTRAINT `FK_9446B734E2779B4D` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE `shop_order` ADD CONSTRAINT `FK_F0782E59BB85D9CC` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE `shop_order` ADD CONSTRAINT `FK_AD73E8E01C396049` FOREIGN KEY (`product_id`) REFERENCES `shop_product` (`id`) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE `subscription` ADD CONSTRAINT `FK_2DCF445D125D7927` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE `user_item` ADD CONSTRAINT `FK_3EA09CD33AC587B7` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE `user_item` ADD CONSTRAINT `FK_8B7212B5BA74D11A` FOREIGN KEY (`product_id`) REFERENCES `shop_product` (`id`) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE `wallet` ADD CONSTRAINT `FK_F60ED11689F25626` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE');
    }

    public function down(): void
    {
        $this->abortIf($this->getPlatformName() !== 'mysql', 'This migration was generated for mysql.');

        $this->addSql('ALTER TABLE `coin_transaction` DROP FOREIGN KEY `FK_9446B734E2779B4D`');
        $this->addSql('ALTER TABLE `shop_order` DROP FOREIGN KEY `FK_F0782E59BB85D9CC`');
        $this->addSql('ALTER TABLE `shop_order` DROP FOREIGN KEY `FK_AD73E8E01C396049`');
        $this->addSql('ALTER TABLE `subscription` DROP FOREIGN KEY `FK_2DCF445D125D7927`');
        $this->addSql('ALTER TABLE `user_item` DROP FOREIGN KEY `FK_3EA09CD33AC587B7`');
        $this->addSql('ALTER TABLE `user_item` DROP FOREIGN KEY `FK_8B7212B5BA74D11A`');
        $this->addSql('ALTER TABLE `wallet` DROP FOREIGN KEY `FK_F60ED11689F25626`');
        $this->addSql('DROP TABLE `coin_transaction`');
        $this->addSql('DROP TABLE `shop_order`');
        $this->addSql('DROP TABLE `shop_product`');
        $this->addSql('DROP TABLE `subscription`');
        $this->addSql('DROP TABLE `user_item`');
        $this->addSql('DROP TABLE `wallet`');
    }
}

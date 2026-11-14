<?php

declare(strict_types=1);

namespace Migrations;

use NeoPHP\Package\Orm\Contract\AbstractMigration;

class Migration_01a1d681ccb7b942 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(): void
    {
        $this->abortIf($this->getPlatformName() !== 'mysql', 'This migration was generated for mysql.');

        $this->addSql('CREATE TABLE `voucher` (`code` VARCHAR(30) NOT NULL, `kind` VARCHAR(10) NOT NULL, `percent` INT DEFAULT NULL, `amount_cents` INT DEFAULT NULL, `coins` INT DEFAULT NULL, `max_uses` INT DEFAULT NULL, `uses` INT NOT NULL, `ends_at` DATETIME DEFAULT NULL, `active` TINYINT(1) NOT NULL, `note` VARCHAR(160) DEFAULT NULL, `id` INT NOT NULL AUTO_INCREMENT, `created_at` DATETIME DEFAULT NULL, `updated_at` DATETIME DEFAULT NULL, `order_id` INT DEFAULT NULL, PRIMARY KEY (`id`)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE UNIQUE INDEX `UNIQ_57F56B8DFD81DC17` ON `voucher` (`code`)');
        $this->addSql('CREATE INDEX `IDX_8B7A4DCC272E98AD` ON `voucher` (`order_id`)');
        $this->addSql('CREATE TABLE `voucher_use` (`id` INT NOT NULL AUTO_INCREMENT, `created_at` DATETIME DEFAULT NULL, `updated_at` DATETIME DEFAULT NULL, `voucher_id` INT NOT NULL, `user_id` INT DEFAULT NULL, `order_id` INT DEFAULT NULL, PRIMARY KEY (`id`)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE INDEX `IDX_D1AC8FB304C23889` ON `voucher_use` (`voucher_id`)');
        $this->addSql('CREATE INDEX `IDX_E68AC4789D36D909` ON `voucher_use` (`user_id`)');
        $this->addSql('CREATE INDEX `IDX_5E75AC622FA520F4` ON `voucher_use` (`order_id`)');
        $this->addSql('ALTER TABLE `shop_order` ADD `voucher_code` VARCHAR(30) DEFAULT NULL');
        $this->addSql('ALTER TABLE `shop_order` ADD `discount_cents` INT DEFAULT NULL');
        $this->addSql('ALTER TABLE `voucher` ADD CONSTRAINT `FK_8B7A4DCC272E98AD` FOREIGN KEY (`order_id`) REFERENCES `shop_order` (`id`) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE `voucher_use` ADD CONSTRAINT `FK_D1AC8FB304C23889` FOREIGN KEY (`voucher_id`) REFERENCES `voucher` (`id`) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE `voucher_use` ADD CONSTRAINT `FK_E68AC4789D36D909` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE `voucher_use` ADD CONSTRAINT `FK_5E75AC622FA520F4` FOREIGN KEY (`order_id`) REFERENCES `shop_order` (`id`) ON DELETE SET NULL');
    }

    public function down(): void
    {
        $this->abortIf($this->getPlatformName() !== 'mysql', 'This migration was generated for mysql.');

        $this->addSql('ALTER TABLE `voucher` DROP FOREIGN KEY `FK_8B7A4DCC272E98AD`');
        $this->addSql('ALTER TABLE `voucher_use` DROP FOREIGN KEY `FK_D1AC8FB304C23889`');
        $this->addSql('ALTER TABLE `voucher_use` DROP FOREIGN KEY `FK_E68AC4789D36D909`');
        $this->addSql('ALTER TABLE `voucher_use` DROP FOREIGN KEY `FK_5E75AC622FA520F4`');
        $this->addSql('DROP TABLE `voucher`');
        $this->addSql('DROP TABLE `voucher_use`');
        $this->addSql('ALTER TABLE `shop_order` DROP `voucher_code`');
        $this->addSql('ALTER TABLE `shop_order` DROP `discount_cents`');
    }
}

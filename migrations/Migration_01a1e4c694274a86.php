<?php

declare(strict_types=1);

namespace Migrations;

use NeoPHP\Package\Orm\Contract\AbstractMigration;

class Migration_01a1e4c694274a86 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(): void
    {
        $this->abortIf($this->getPlatformName() !== 'mysql', 'This migration was generated for mysql.');

        $this->addSql('CREATE TABLE `subscription_gift` (`claim_key` VARCHAR(64) NOT NULL, `offer_name` VARCHAR(80) NOT NULL, `items` JSON NOT NULL, `id` INT NOT NULL AUTO_INCREMENT, `created_at` DATETIME DEFAULT NULL, `updated_at` DATETIME DEFAULT NULL, `user_id` INT NOT NULL, `offer_id` INT DEFAULT NULL, `source_order_id` INT DEFAULT NULL, PRIMARY KEY (`id`)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE UNIQUE INDEX `UNIQ_06A0A9C054AD8443` ON `subscription_gift` (`claim_key`)');
        $this->addSql('CREATE INDEX `IDX_800F58D2EF68191E` ON `subscription_gift` (`user_id`)');
        $this->addSql('CREATE INDEX `IDX_9188CCC0AC50AFE8` ON `subscription_gift` (`offer_id`)');
        $this->addSql('CREATE INDEX `IDX_4F9B76320DDCB942` ON `subscription_gift` (`source_order_id`)');
        $this->addSql('ALTER TABLE `shop_order` ADD `included_gifts` JSON DEFAULT NULL');
        $this->addSql('ALTER TABLE `subscription_gift` ADD CONSTRAINT `FK_800F58D2EF68191E` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE `subscription_gift` ADD CONSTRAINT `FK_9188CCC0AC50AFE8` FOREIGN KEY (`offer_id`) REFERENCES `shop_product` (`id`) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE `subscription_gift` ADD CONSTRAINT `FK_4F9B76320DDCB942` FOREIGN KEY (`source_order_id`) REFERENCES `shop_order` (`id`) ON DELETE SET NULL');
    }

    public function down(): void
    {
        $this->abortIf($this->getPlatformName() !== 'mysql', 'This migration was generated for mysql.');

        $this->addSql('ALTER TABLE `subscription_gift` DROP FOREIGN KEY `FK_800F58D2EF68191E`');
        $this->addSql('ALTER TABLE `subscription_gift` DROP FOREIGN KEY `FK_9188CCC0AC50AFE8`');
        $this->addSql('ALTER TABLE `subscription_gift` DROP FOREIGN KEY `FK_4F9B76320DDCB942`');
        $this->addSql('DROP TABLE `subscription_gift`');
        $this->addSql('ALTER TABLE `shop_order` DROP `included_gifts`');
    }
}

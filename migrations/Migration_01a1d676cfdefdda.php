<?php

declare(strict_types=1);

namespace Migrations;

use NeoPHP\Package\Orm\Contract\AbstractMigration;

class Migration_01a1d676cfdefdda extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(): void
    {
        $this->abortIf($this->getPlatformName() !== 'mysql', 'This migration was generated for mysql.');

        $this->addSql('CREATE TABLE `invoice` (`number` VARCHAR(20) NOT NULL, `kind` VARCHAR(10) NOT NULL, `reference` VARCHAR(20) DEFAULT NULL, `seller` JSON NOT NULL, `buyer` JSON NOT NULL, `lines` JSON NOT NULL, `total_cents` INT NOT NULL, `vat_cents` INT NOT NULL, `vat_rate` DOUBLE PRECISION NOT NULL, `issued_at` DATETIME NOT NULL, `id` INT NOT NULL AUTO_INCREMENT, `created_at` DATETIME DEFAULT NULL, `updated_at` DATETIME DEFAULT NULL, `order_id` INT DEFAULT NULL, `user_id` INT DEFAULT NULL, PRIMARY KEY (`id`)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE UNIQUE INDEX `UNIQ_977A8123A9F8B1A6` ON `invoice` (`number`)');
        $this->addSql('CREATE INDEX `IDX_F29AF13BB7DAC0AC` ON `invoice` (`order_id`)');
        $this->addSql('CREATE INDEX `IDX_93F617A30CBA3DA7` ON `invoice` (`user_id`)');
        $this->addSql('ALTER TABLE `shop_order` ADD `variant` VARCHAR(60) DEFAULT NULL');
        $this->addSql('ALTER TABLE `shop_order` ADD `terms_accepted_at` DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE `invoice` ADD CONSTRAINT `FK_F29AF13BB7DAC0AC` FOREIGN KEY (`order_id`) REFERENCES `shop_order` (`id`) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE `invoice` ADD CONSTRAINT `FK_93F617A30CBA3DA7` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE SET NULL');
    }

    public function down(): void
    {
        $this->abortIf($this->getPlatformName() !== 'mysql', 'This migration was generated for mysql.');

        $this->addSql('ALTER TABLE `invoice` DROP FOREIGN KEY `FK_F29AF13BB7DAC0AC`');
        $this->addSql('ALTER TABLE `invoice` DROP FOREIGN KEY `FK_93F617A30CBA3DA7`');
        $this->addSql('DROP TABLE `invoice`');
        $this->addSql('ALTER TABLE `shop_order` DROP `variant`');
        $this->addSql('ALTER TABLE `shop_order` DROP `terms_accepted_at`');
    }
}

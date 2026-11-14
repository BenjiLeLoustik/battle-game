<?php

declare(strict_types=1);

namespace Migrations;

use NeoPHP\Package\Orm\Contract\AbstractMigration;

class Migration_01a1d537e856e5f2 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(): void
    {
        $this->abortIf($this->getPlatformName() !== 'mysql', 'This migration was generated for mysql.');

        $this->addSql('ALTER TABLE `subscription` ADD `name` VARCHAR(80) NOT NULL');
        $this->addSql('ALTER TABLE `subscription` ADD `product_id` INT DEFAULT NULL');
        $this->addSql('CREATE INDEX `IDX_B692BF5E221FF07B` ON `subscription` (`product_id`)');
        $this->addSql('ALTER TABLE `subscription` DROP `plan`');
        $this->addSql('ALTER TABLE `subscription` ADD CONSTRAINT `FK_B692BF5E221FF07B` FOREIGN KEY (`product_id`) REFERENCES `shop_product` (`id`) ON DELETE SET NULL');
    }

    public function down(): void
    {
        $this->abortIf($this->getPlatformName() !== 'mysql', 'This migration was generated for mysql.');

        $this->addSql('ALTER TABLE `subscription` DROP FOREIGN KEY `FK_B692BF5E221FF07B`');
        $this->addSql('ALTER TABLE `subscription` ADD `plan` VARCHAR(20) NOT NULL');
        $this->addSql('DROP INDEX `IDX_B692BF5E221FF07B` ON `subscription`');
        $this->addSql('ALTER TABLE `subscription` DROP `name`');
        $this->addSql('ALTER TABLE `subscription` DROP `product_id`');
    }
}

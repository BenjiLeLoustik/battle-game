<?php

declare(strict_types=1);

namespace Migrations;

use NeoPHP\Package\Orm\Contract\AbstractMigration;

class Migration_01a1cbc3af7e3a49 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(): void
    {
        $this->abortIf($this->getPlatformName() !== 'mysql', 'This migration was generated for mysql.');

        $this->addSql('CREATE TABLE `page` (`title` VARCHAR(120) NOT NULL, `path` VARCHAR(180) NOT NULL, `description` VARCHAR(160) NOT NULL, `format` VARCHAR(20) NOT NULL, `content` LONGTEXT NOT NULL, `style` LONGTEXT NOT NULL, `footer` VARCHAR(20) NOT NULL, `position` INT NOT NULL, `published` TINYINT(1) NOT NULL, `id` INT NOT NULL AUTO_INCREMENT, `created_at` DATETIME DEFAULT NULL, `updated_at` DATETIME DEFAULT NULL, PRIMARY KEY (`id`)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE UNIQUE INDEX `UNIQ_D26BC6E5267EFBA0` ON `page` (`path`)');
    }

    public function down(): void
    {
        $this->abortIf($this->getPlatformName() !== 'mysql', 'This migration was generated for mysql.');

        $this->addSql('DROP TABLE `page`');
    }
}

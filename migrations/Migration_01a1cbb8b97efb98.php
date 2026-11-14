<?php

declare(strict_types=1);

namespace Migrations;

use NeoPHP\Package\Orm\Contract\AbstractMigration;

class Migration_01a1cbb8b97efb98 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(): void
    {
        $this->abortIf($this->getPlatformName() !== 'mysql', 'This migration was generated for mysql.');

        $this->addSql('CREATE TABLE `content_translation` (`type` VARCHAR(40) NOT NULL, `object_id` INT NOT NULL, `locale` VARCHAR(5) NOT NULL, `field` VARCHAR(40) NOT NULL, `value` LONGTEXT NOT NULL, `id` INT NOT NULL AUTO_INCREMENT, PRIMARY KEY (`id`)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE UNIQUE INDEX `UNIQ_72254B625A7F71DE` ON `content_translation` (`type`, `object_id`, `locale`, `field`)');
    }

    public function down(): void
    {
        $this->abortIf($this->getPlatformName() !== 'mysql', 'This migration was generated for mysql.');

        $this->addSql('DROP TABLE `content_translation`');
    }
}

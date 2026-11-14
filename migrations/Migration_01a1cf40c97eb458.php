<?php

declare(strict_types=1);

namespace Migrations;

use NeoPHP\Package\Orm\Contract\AbstractMigration;

class Migration_01a1cf40c97eb458 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(): void
    {
        $this->abortIf($this->getPlatformName() !== 'mysql', 'This migration was generated for mysql.');

        $this->addSql('CREATE TABLE `chat_channel` (`slug` VARCHAR(40) NOT NULL, `name` VARCHAR(60) NOT NULL, `description` VARCHAR(160) NOT NULL, `read_access` VARCHAR(20) NOT NULL, `write_access` VARCHAR(20) NOT NULL, `cooldown` INT NOT NULL, `position` INT NOT NULL, `active` TINYINT(1) NOT NULL, `id` INT NOT NULL AUTO_INCREMENT, `created_at` DATETIME DEFAULT NULL, `updated_at` DATETIME DEFAULT NULL, PRIMARY KEY (`id`)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE UNIQUE INDEX `UNIQ_CBFBA100F9593EDD` ON `chat_channel` (`slug`)');
    }

    public function down(): void
    {
        $this->abortIf($this->getPlatformName() !== 'mysql', 'This migration was generated for mysql.');

        $this->addSql('DROP TABLE `chat_channel`');
    }
}

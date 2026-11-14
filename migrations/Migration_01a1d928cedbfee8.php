<?php

declare(strict_types=1);

namespace Migrations;

use NeoPHP\Package\Orm\Contract\AbstractMigration;

class Migration_01a1d928cedbfee8 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(): void
    {
        $this->abortIf($this->getPlatformName() !== 'mysql', 'This migration was generated for mysql.');

        $this->addSql('CREATE TABLE `pass_plan` (`name` VARCHAR(80) NOT NULL, `tiers` JSON NOT NULL, `id` INT NOT NULL AUTO_INCREMENT, `created_at` DATETIME DEFAULT NULL, `updated_at` DATETIME DEFAULT NULL, PRIMARY KEY (`id`)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE `season` ADD `pass_id` INT DEFAULT NULL');
        $this->addSql('CREATE INDEX `IDX_50DC9F95AD44D034` ON `season` (`pass_id`)');
        $this->addSql('ALTER TABLE `season` ADD CONSTRAINT `FK_50DC9F95AD44D034` FOREIGN KEY (`pass_id`) REFERENCES `pass_plan` (`id`) ON DELETE SET NULL');
    }

    public function down(): void
    {
        $this->abortIf($this->getPlatformName() !== 'mysql', 'This migration was generated for mysql.');

        $this->addSql('ALTER TABLE `season` DROP FOREIGN KEY `FK_50DC9F95AD44D034`');
        $this->addSql('DROP TABLE `pass_plan`');
        $this->addSql('DROP INDEX `IDX_50DC9F95AD44D034` ON `season`');
        $this->addSql('ALTER TABLE `season` DROP `pass_id`');
    }
}

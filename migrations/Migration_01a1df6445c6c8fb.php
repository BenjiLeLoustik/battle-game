<?php

declare(strict_types=1);

namespace Migrations;

use NeoPHP\Package\Orm\Contract\AbstractMigration;

class Migration_01a1df6445c6c8fb extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(): void
    {
        $this->abortIf($this->getPlatformName() !== 'mysql', 'This migration was generated for mysql.');

        $this->addSql('CREATE TABLE `level_definition` (`number` INT NOT NULL, `name` VARCHAR(80) NOT NULL, `required_experience` INT NOT NULL, `id` INT NOT NULL AUTO_INCREMENT, `badge_id` INT DEFAULT NULL, PRIMARY KEY (`id`)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE UNIQUE INDEX `UNIQ_632112E519AFCAD1` ON `level_definition` (`number`)');
        $this->addSql('CREATE INDEX `IDX_9F16AD30DD943756` ON `level_definition` (`badge_id`)');
        $this->addSql('ALTER TABLE `tournament` MODIFY `qualifier` VARCHAR(10) NOT NULL');
        $this->addSql('ALTER TABLE `tournament` MODIFY `qualifier_period` VARCHAR(10) NOT NULL');
        $this->addSql('ALTER TABLE `level_definition` ADD CONSTRAINT `FK_9F16AD30DD943756` FOREIGN KEY (`badge_id`) REFERENCES `badge` (`id`) ON DELETE SET NULL');
    }

    public function down(): void
    {
        $this->abortIf($this->getPlatformName() !== 'mysql', 'This migration was generated for mysql.');

        $this->addSql('ALTER TABLE `level_definition` DROP FOREIGN KEY `FK_9F16AD30DD943756`');
        $this->addSql('DROP TABLE `level_definition`');
        $this->addSql('ALTER TABLE `tournament` MODIFY `qualifier` VARCHAR(10) NOT NULL DEFAULT \'open\'');
        $this->addSql('ALTER TABLE `tournament` MODIFY `qualifier_period` VARCHAR(10) NOT NULL DEFAULT \'season\'');
    }
}

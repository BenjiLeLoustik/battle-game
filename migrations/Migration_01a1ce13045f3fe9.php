<?php

declare(strict_types=1);

namespace Migrations;

use NeoPHP\Package\Orm\Contract\AbstractMigration;

class Migration_01a1ce13045f3fe9 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(): void
    {
        $this->abortIf($this->getPlatformName() !== 'mysql', 'This migration was generated for mysql.');

        $this->addSql('CREATE TABLE `country` (`code` VARCHAR(2) NOT NULL, `name` VARCHAR(60) NOT NULL, `locale` VARCHAR(5) NOT NULL, `id` INT NOT NULL AUTO_INCREMENT, `created_at` DATETIME DEFAULT NULL, `updated_at` DATETIME DEFAULT NULL, PRIMARY KEY (`id`)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE UNIQUE INDEX `UNIQ_034C606710119F15` ON `country` (`code`)');
        $this->addSql("INSERT INTO `country` (`code`, `name`, `locale`, `created_at`) VALUES ('FR', 'France', 'fr', NOW()), ('BE', 'Belgique', 'fr', NOW()), ('CH', 'Suisse', 'fr', NOW()), ('CA', 'Canada', 'fr', NOW())");
    }

    public function down(): void
    {
        $this->abortIf($this->getPlatformName() !== 'mysql', 'This migration was generated for mysql.');

        $this->addSql('DROP TABLE `country`');
    }
}

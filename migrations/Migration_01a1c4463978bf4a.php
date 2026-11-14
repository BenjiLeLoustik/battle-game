<?php

declare(strict_types=1);

namespace Migrations;

use NeoPHP\Package\Orm\Contract\AbstractMigration;

class Migration_01a1c4463978bf4a extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(): void
    {
        $this->abortIf($this->getPlatformName() !== 'mysql', 'This migration was generated for mysql.');

        $this->addSql('CREATE TABLE `user` (`first_name` VARCHAR(50) NOT NULL, `last_name` VARCHAR(50) NOT NULL, `username` VARCHAR(30) NOT NULL, `email` VARCHAR(180) NOT NULL, `roles` JSON NOT NULL, `password` VARCHAR(255) DEFAULT NULL, `discord_id` VARCHAR(32) DEFAULT NULL, `avatar` VARCHAR(255) DEFAULT NULL, `id` INT NOT NULL AUTO_INCREMENT, `created_at` DATETIME DEFAULT NULL, `updated_at` DATETIME DEFAULT NULL, PRIMARY KEY (`id`)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE UNIQUE INDEX `UNIQ_739BABD74995A117` ON `user` (`username`)');
        $this->addSql('CREATE UNIQUE INDEX `UNIQ_B06F65EE32AF150F` ON `user` (`email`)');
        $this->addSql('CREATE UNIQUE INDEX `UNIQ_41CEC36A153648F7` ON `user` (`discord_id`)');
    }

    public function down(): void
    {
        $this->abortIf($this->getPlatformName() !== 'mysql', 'This migration was generated for mysql.');

        $this->addSql('DROP TABLE `user`');
    }
}

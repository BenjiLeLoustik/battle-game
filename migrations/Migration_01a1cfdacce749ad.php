<?php

declare(strict_types=1);

namespace Migrations;

use NeoPHP\Package\Orm\Contract\AbstractMigration;

class Migration_01a1cfdacce749ad extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(): void
    {
        $this->abortIf($this->getPlatformName() !== 'mysql', 'This migration was generated for mysql.');

        $this->addSql('CREATE TABLE `game_task` (`title` VARCHAR(120) NOT NULL, `description` LONGTEXT NOT NULL, `checklist` JSON NOT NULL, `status` VARCHAR(20) NOT NULL, `start_at` DATE DEFAULT NULL, `end_at` DATE DEFAULT NULL, `id` INT NOT NULL AUTO_INCREMENT, `created_at` DATETIME DEFAULT NULL, `updated_at` DATETIME DEFAULT NULL, `game_id` INT NOT NULL, `author_id` INT DEFAULT NULL, PRIMARY KEY (`id`)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE INDEX `IDX_CA09D24BA779F029` ON `game_task` (`game_id`)');
        $this->addSql('CREATE INDEX `IDX_191DADF79412E19D` ON `game_task` (`author_id`)');
        $this->addSql('CREATE TABLE `game_task_user` (`game_task_id` INT NOT NULL, `user_id` INT NOT NULL, PRIMARY KEY (`game_task_id`, `user_id`)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE INDEX `IDX_EC50BFBBF9108BA1` ON `game_task_user` (`user_id`)');
        $this->addSql('ALTER TABLE `game_task` ADD CONSTRAINT `FK_CA09D24BA779F029` FOREIGN KEY (`game_id`) REFERENCES `game` (`id`) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE `game_task` ADD CONSTRAINT `FK_191DADF79412E19D` FOREIGN KEY (`author_id`) REFERENCES `user` (`id`) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE `game_task_user` ADD CONSTRAINT `FK_A6C37748392432D5` FOREIGN KEY (`game_task_id`) REFERENCES `game_task` (`id`) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE `game_task_user` ADD CONSTRAINT `FK_EC50BFBBF9108BA1` FOREIGN KEY (`user_id`) REFERENCES `user` (`id`) ON DELETE CASCADE');
    }

    public function down(): void
    {
        $this->abortIf($this->getPlatformName() !== 'mysql', 'This migration was generated for mysql.');

        $this->addSql('ALTER TABLE `game_task` DROP FOREIGN KEY `FK_CA09D24BA779F029`');
        $this->addSql('ALTER TABLE `game_task` DROP FOREIGN KEY `FK_191DADF79412E19D`');
        $this->addSql('ALTER TABLE `game_task_user` DROP FOREIGN KEY `FK_A6C37748392432D5`');
        $this->addSql('ALTER TABLE `game_task_user` DROP FOREIGN KEY `FK_EC50BFBBF9108BA1`');
        $this->addSql('DROP TABLE `game_task`');
        $this->addSql('DROP TABLE `game_task_user`');
    }
}

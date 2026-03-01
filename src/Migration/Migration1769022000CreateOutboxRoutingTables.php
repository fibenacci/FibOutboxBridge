<?php

declare(strict_types=1);

namespace Fib\OutboxBridge\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1769022000CreateOutboxRoutingTables extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1769022000;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<SQL
                CREATE TABLE IF NOT EXISTS `fib_outbox_target` (
                    `id` CHAR(32) NOT NULL,
                    `name` VARCHAR(191) NOT NULL,
                    `technical_name` VARCHAR(100) NOT NULL,
                    `type` VARCHAR(32) NOT NULL,
                    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                    `config` JSON NULL,
                    `created_at` DATETIME(3) NOT NULL,
                    `updated_at` DATETIME(3) NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uniq.fib_outbox_target.technical_name` (`technical_name`),
                    KEY `idx.fib_outbox_target.active_type` (`is_active`, `type`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            SQL);

        $connection->executeStatement(<<<SQL
                CREATE TABLE IF NOT EXISTS `fib_outbox_route` (
                    `id` CHAR(32) NOT NULL,
                    `name` VARCHAR(191) NOT NULL,
                    `event_pattern` VARCHAR(191) NOT NULL,
                    `priority` INT NOT NULL DEFAULT 100,
                    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                    `target_keys` JSON NOT NULL,
                    `created_at` DATETIME(3) NOT NULL,
                    `updated_at` DATETIME(3) NULL,
                    PRIMARY KEY (`id`),
                    KEY `idx.fib_outbox_route.active_priority` (`is_active`, `priority`),
                    KEY `idx.fib_outbox_route.event_pattern` (`event_pattern`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}

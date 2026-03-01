<?php

declare(strict_types=1);

namespace Fib\OutboxBridge\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1769023000CreateOutboxDestinationAndDeliveryReference extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1769023000;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<SQL
                CREATE TABLE IF NOT EXISTS `fib_outbox_destination` (
                    `id` CHAR(32) NOT NULL,
                    `name` VARCHAR(191) NOT NULL,
                    `technical_name` VARCHAR(100) NOT NULL,
                    `type` VARCHAR(32) NOT NULL,
                    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
                    `config` JSON NULL,
                    `created_at` DATETIME(3) NOT NULL,
                    `updated_at` DATETIME(3) NULL,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uniq.fib_outbox_destination.technical_name` (`technical_name`),
                    KEY `idx.fib_outbox_destination.active_type` (`is_active`, `type`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
            SQL);

        $targetTableExists = (int) $connection->fetchOne(<<<SQL
                SELECT COUNT(*)
                FROM information_schema.tables
                WHERE table_schema = DATABASE()
                  AND table_name = 'fib_outbox_target'
            SQL) > 0;

        if ($targetTableExists) {
            $connection->executeStatement(<<<SQL
                    INSERT IGNORE INTO `fib_outbox_destination` (`id`, `name`, `technical_name`, `type`, `is_active`, `config`, `created_at`, `updated_at`)
                    SELECT `id`, `name`, `technical_name`, `type`, `is_active`, `config`, `created_at`, `updated_at`
                    FROM `fib_outbox_target`
                SQL);
        }

        $destinationColumnExists = (int) $connection->fetchOne(<<<SQL
                SELECT COUNT(*)
                FROM information_schema.columns
                WHERE table_schema = DATABASE()
                  AND table_name = 'fib_outbox_delivery'
                  AND column_name = 'destination_id'
            SQL) > 0;

        if (!$destinationColumnExists) {
            $connection->executeStatement(<<<SQL
                    ALTER TABLE `fib_outbox_delivery`
                    ADD COLUMN `destination_id` CHAR(32) NULL AFTER `event_id`
                SQL);
        }

        $connection->executeStatement(<<<SQL
                UPDATE `fib_outbox_delivery` d
                INNER JOIN `fib_outbox_destination` dst ON dst.`technical_name` = d.`target_key`
                SET d.`destination_id` = dst.`id`
                WHERE d.`destination_id` IS NULL
            SQL);

        $indexExists = (int) $connection->fetchOne(<<<SQL
                SELECT COUNT(*)
                FROM information_schema.statistics
                WHERE table_schema = DATABASE()
                  AND table_name = 'fib_outbox_delivery'
                  AND index_name = 'idx.fib_outbox_delivery.destination_id'
            SQL) > 0;

        if (!$indexExists) {
            $connection->executeStatement(<<<SQL
                    CREATE INDEX `idx.fib_outbox_delivery.destination_id`
                    ON `fib_outbox_delivery` (`destination_id`)
                SQL);
        }
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}

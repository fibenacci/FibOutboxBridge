<?php declare(strict_types=1);

namespace Fib\OutboxBridge\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1769021000CreateOutboxDeliveryTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1769021000;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<SQL
            CREATE TABLE IF NOT EXISTS `fib_outbox_delivery` (
                `id` CHAR(32) NOT NULL,
                `event_id` CHAR(32) NOT NULL,
                `target_key` VARCHAR(100) NOT NULL,
                `target_type` VARCHAR(32) NOT NULL,
                `target_config` JSON NULL,
                `status` VARCHAR(16) NOT NULL,
                `attempts` INT UNSIGNED NOT NULL DEFAULT 0,
                `available_at` DATETIME(3) NOT NULL,
                `published_at` DATETIME(3) NULL,
                `locked_until` DATETIME(3) NULL,
                `lock_owner` VARCHAR(128) NULL,
                `last_error` TEXT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NOT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.fib_outbox_delivery.event_target` (`event_id`, `target_key`),
                KEY `idx.fib_outbox_delivery.status_available` (`status`, `available_at`),
                KEY `idx.fib_outbox_delivery.locked_until` (`locked_until`),
                CONSTRAINT `fk.fib_outbox_delivery.event_id` FOREIGN KEY (`event_id`)
                    REFERENCES `fib_outbox_event` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}

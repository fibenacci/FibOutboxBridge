<?php declare(strict_types=1);

namespace Fib\OutboxBridge\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1769020000CreateOutboxTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1769020000;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement(<<<SQL
            CREATE TABLE IF NOT EXISTS `fib_outbox_event` (
                `id` CHAR(32) NOT NULL,
                `event_name` VARCHAR(191) NOT NULL,
                `aggregate_type` VARCHAR(100) NOT NULL,
                `aggregate_id` VARCHAR(100) NOT NULL,
                `payload` JSON NOT NULL,
                `meta` JSON NULL,
                `occurred_at` DATETIME(3) NOT NULL,
                `available_at` DATETIME(3) NOT NULL,
                `published_at` DATETIME(3) NULL,
                `status` VARCHAR(16) NOT NULL,
                `attempts` INT UNSIGNED NOT NULL DEFAULT 0,
                `locked_until` DATETIME(3) NULL,
                `lock_owner` VARCHAR(128) NULL,
                `last_error` TEXT NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NOT NULL,
                PRIMARY KEY (`id`),
                KEY `idx.fib_outbox_event.status_available` (`status`, `available_at`),
                KEY `idx.fib_outbox_event.locked_until` (`locked_until`),
                KEY `idx.fib_outbox_event.aggregate_debug` (`aggregate_type`, `aggregate_id`, `occurred_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        SQL);
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}

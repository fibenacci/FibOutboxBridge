<?php declare(strict_types=1);

namespace Fib\OutboxBridge\Core\Outbox\Repository;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Fib\OutboxBridge\Core\Outbox\Domain\DomainEvent;
use Shopware\Core\Defaults;

class OutboxRepository
{
    public const TABLE = 'fib_outbox_event';
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_FAILED = 'failed';
    public const STATUS_DEAD = 'dead';

    public function __construct(
        private readonly Connection $connection
    ) {
    }

    public function append(DomainEvent $event): void
    {
        $this->appendMany([$event]);
    }

    /**
     * @param list<DomainEvent> $events
     */
    public function appendMany(array $events): int
    {
        if ($events === []) {
            return 0;
        }

        $now = new \DateTimeImmutable();
        $count = 0;

        foreach ($events as $event) {
            $this->connection->insert(self::TABLE, [
                'id' => $event->getId(),
                'event_name' => $event->getEventName(),
                'aggregate_type' => $event->getAggregateType(),
                'aggregate_id' => $event->getAggregateId(),
                'payload' => json_encode($event->getPayload(), \JSON_THROW_ON_ERROR),
                'meta' => $event->getMeta() === null ? null : json_encode($event->getMeta(), \JSON_THROW_ON_ERROR),
                'occurred_at' => $event->getOccurredAt()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'available_at' => $event->getOccurredAt()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'published_at' => null,
                'status' => self::STATUS_PENDING,
                'attempts' => 0,
                'locked_until' => null,
                'lock_owner' => null,
                'last_error' => null,
                'created_at' => $now->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'updated_at' => $now->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);

            ++$count;
        }

        return $count;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function claimBatch(int $limit, string $workerId, int $lockSeconds): array
    {
        $limit = max(1, $limit);
        $now = new \DateTimeImmutable();
        $lockUntil = $now->modify(sprintf('+%d seconds', $lockSeconds));
        $claimOwner = sprintf('%s:%s', $workerId, bin2hex(random_bytes(4)));

        try {
            return $this->claimBatchWithSkipLocked($limit, $claimOwner, $now, $lockUntil);
        } catch (Exception $exception) {
            return $this->claimBatchOptimistic($limit, $claimOwner, $now, $lockUntil);
        }
    }

    public function markPublished(string $id): void
    {
        $now = (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $this->connection->update(self::TABLE, [
            'status' => self::STATUS_PUBLISHED,
            'published_at' => $now,
            'locked_until' => null,
            'lock_owner' => null,
            'last_error' => null,
            'updated_at' => $now,
        ], ['id' => $id]);
    }

    public function reschedule(
        string $id,
        int $attempts,
        \DateTimeImmutable $availableAt,
        string $errorMessage
    ): void {
        $now = (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $this->connection->update(self::TABLE, [
            'status' => self::STATUS_PENDING,
            'attempts' => $attempts,
            'available_at' => $availableAt->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'locked_until' => null,
            'lock_owner' => null,
            'last_error' => mb_substr($errorMessage, 0, 65000),
            'updated_at' => $now,
        ], ['id' => $id]);
    }

    public function markDead(
        string $id,
        int $attempts,
        string $errorMessage
    ): void {
        $now = (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $this->connection->update(self::TABLE, [
            'status' => self::STATUS_DEAD,
            'attempts' => $attempts,
            'locked_until' => null,
            'lock_owner' => null,
            'last_error' => mb_substr($errorMessage, 0, 65000),
            'updated_at' => $now,
        ], ['id' => $id]);
    }

    public function resetExpiredProcessingLocks(): int
    {
        $now = (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        return $this->connection->executeStatement(<<<SQL
            UPDATE `fib_outbox_event`
            SET
                `status` = :pendingStatus,
                `locked_until` = NULL,
                `lock_owner` = NULL,
                `updated_at` = :now
            WHERE `status` = :processingStatus
              AND `locked_until` IS NOT NULL
              AND `locked_until` < :now
        SQL, [
            'pendingStatus' => self::STATUS_PENDING,
            'processingStatus' => self::STATUS_PROCESSING,
            'now' => $now,
        ]);
    }

    public function requeueDead(int $limit = 100, ?string $eventName = null): int
    {
        $limit = max(1, min(1000, $limit));
        $now = new \DateTimeImmutable();

        $query = <<<SQL
            SELECT `id`
            FROM `fib_outbox_event`
            WHERE `status` = :status
        SQL;

        $params = [
            'status' => self::STATUS_DEAD,
        ];

        if (!empty($eventName)) {
            $query .= "\n  AND `event_name` = :eventName";
            $params['eventName'] = $eventName;
        }

        $query .= <<<SQL

            ORDER BY `updated_at` DESC, `occurred_at` DESC, `id` DESC
            LIMIT {$limit}
        SQL;

        $ids = $this->connection->fetchFirstColumn($query, $params);

        if (empty($ids)) {
            return 0;
        }

        return $this->connection->executeStatement(<<<SQL
            UPDATE `fib_outbox_event`
            SET
                `status` = :pending,
                `attempts` = 0,
                `available_at` = :now,
                `locked_until` = NULL,
                `lock_owner` = NULL,
                `last_error` = NULL,
                `updated_at` = :now
            WHERE `id` IN (:ids)
        SQL, [
            'pending' => self::STATUS_PENDING,
            'now' => $now->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'ids' => array_values(array_map('strval', $ids)),
        ], [
            'ids' => ArrayParameterType::STRING,
        ]);
    }

    /**
     * @return array<string, int>
     */
    public function getStatusCounts(): array
    {
        $rows = $this->connection->fetchAllAssociative(<<<SQL
            SELECT `status`, COUNT(*) AS `cnt`
            FROM `fib_outbox_event`
            GROUP BY `status`
        SQL);

        $counts = [
            self::STATUS_PENDING => 0,
            self::STATUS_PROCESSING => 0,
            self::STATUS_PUBLISHED => 0,
            self::STATUS_FAILED => 0,
            self::STATUS_DEAD => 0,
        ];

        foreach ($rows as $row) {
            $status = (string) ($row['status'] ?? '');
            if (!array_key_exists($status, $counts)) {
                continue;
            }

            $counts[$status] = (int) ($row['cnt'] ?? 0);
        }

        return $counts;
    }

    public function getOldestPendingLagSeconds(): ?int
    {
        $value = $this->connection->fetchOne(<<<SQL
            SELECT TIMESTAMPDIFF(SECOND, MIN(`occurred_at`), NOW(3))
            FROM `fib_outbox_event`
            WHERE `status` = :status
        SQL, [
            'status' => self::STATUS_PENDING,
        ]);

        if (empty($value)) {
            return null;
        }

        return max(0, (int) $value);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function claimBatchWithSkipLocked(
        int $limit,
        string $claimOwner,
        \DateTimeImmutable $now,
        \DateTimeImmutable $lockUntil
    ): array {
        $this->connection->beginTransaction();

        try {
            $ids = $this->connection->fetchFirstColumn(<<<SQL
                SELECT `id`
                FROM `fib_outbox_event`
                WHERE `status` = :status
                  AND `available_at` <= :now
                  AND (`locked_until` IS NULL OR `locked_until` < :now)
                ORDER BY `available_at` ASC, `occurred_at` ASC, `id` ASC
                LIMIT {$limit}
                FOR UPDATE SKIP LOCKED
            SQL, [
                'status' => self::STATUS_PENDING,
                'now' => $now->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);

            if (empty($ids)) {
                $this->connection->commit();

                return [];
            }

            $this->connection->executeStatement(<<<SQL
                UPDATE `fib_outbox_event`
                SET
                    `status` = :processing,
                    `lock_owner` = :lockOwner,
                    `locked_until` = :lockedUntil,
                    `updated_at` = :updatedAt
                WHERE `id` IN (:ids)
            SQL, [
                'processing' => self::STATUS_PROCESSING,
                'lockOwner' => $claimOwner,
                'lockedUntil' => $lockUntil->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'updatedAt' => $now->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'ids' => array_values(array_map('strval', $ids)),
            ], [
                'ids' => ArrayParameterType::STRING,
            ]);

            $rows = $this->connection->fetchAllAssociative(<<<SQL
                SELECT *
                FROM `fib_outbox_event`
                WHERE `lock_owner` = :lockOwner
                ORDER BY `available_at` ASC, `occurred_at` ASC, `id` ASC
            SQL, [
                'lockOwner' => $claimOwner,
            ]);

            $this->connection->commit();

            return $rows;
        } catch (\Throwable $exception) {
            $this->connection->rollBack();

            throw $exception;
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function claimBatchOptimistic(
        int $limit,
        string $claimOwner,
        \DateTimeImmutable $now,
        \DateTimeImmutable $lockUntil
    ): array {
        $ids = $this->connection->fetchFirstColumn(<<<SQL
            SELECT `id`
            FROM `fib_outbox_event`
            WHERE `status` = :status
              AND `available_at` <= :now
              AND (`locked_until` IS NULL OR `locked_until` < :now)
            ORDER BY `available_at` ASC, `occurred_at` ASC, `id` ASC
            LIMIT {$limit}
        SQL, [
            'status' => self::STATUS_PENDING,
            'now' => $now->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        if (empty($ids)) {
            return [];
        }

        $this->connection->executeStatement(<<<SQL
            UPDATE `fib_outbox_event`
            SET
                `status` = :processing,
                `lock_owner` = :lockOwner,
                `locked_until` = :lockedUntil,
                `updated_at` = :updatedAt
            WHERE `id` IN (:ids)
              AND `status` = :pending
              AND `available_at` <= :now
              AND (`locked_until` IS NULL OR `locked_until` < :now)
        SQL, [
            'processing' => self::STATUS_PROCESSING,
            'lockOwner' => $claimOwner,
            'lockedUntil' => $lockUntil->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'updatedAt' => $now->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'pending' => self::STATUS_PENDING,
            'now' => $now->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'ids' => array_values(array_map('strval', $ids)),
        ], [
            'ids' => ArrayParameterType::STRING,
        ]);

        return $this->connection->fetchAllAssociative(<<<SQL
            SELECT *
            FROM `fib_outbox_event`
            WHERE `lock_owner` = :lockOwner
            ORDER BY `available_at` ASC, `occurred_at` ASC, `id` ASC
        SQL, [
            'lockOwner' => $claimOwner,
        ]);
    }
}

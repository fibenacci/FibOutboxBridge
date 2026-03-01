<?php declare(strict_types=1);

namespace Fib\OutboxBridge\Core\Outbox\Repository;

use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Fib\OutboxBridge\Core\Outbox\Domain\DomainEvent;
use Fib\OutboxBridge\Core\Outbox\Routing\OutboxRouteResolver;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;

class OutboxRepository
{
    public const TABLE = 'fib_outbox_event';
    public const DELIVERY_TABLE = 'fib_outbox_delivery';

    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_PUBLISHED = 'published';
    public const STATUS_FAILED = 'failed';
    public const STATUS_DEAD = 'dead';

    private ?bool $deliveryHasDestinationIdColumn = null;

    public function __construct(
        private readonly Connection $connection,
        private readonly OutboxRouteResolver $routeResolver
    ) {
    }

    public function append(DomainEvent $event): void
    {
        $this->appendMany([$event]);
    }

    /**
     * @param list<array{id: string, key: string, type: string, config: array<string, mixed>}> $destinations
     */
    public function appendWithDestinations(DomainEvent $event, array $destinations): void
    {
        if ($destinations === []) {
            return;
        }

        $now = new \DateTimeImmutable();

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

        $this->insertDeliveries($event->getId(), $destinations, $now);
        $this->syncEventStatus($event->getId());
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

            $this->createDeliveriesForEvent($event, $now);
        }

        return count($events);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function claimDeliveryBatch(int $limit, string $workerId, int $lockSeconds): array
    {
        $limit = max(1, $limit);
        $now = new \DateTimeImmutable();
        $lockUntil = $now->modify(sprintf('+%d seconds', $lockSeconds));
        $claimOwner = sprintf('%s:%s', $workerId, bin2hex(random_bytes(4)));

        return $this->claimDeliveryBatchOptimistic($limit, $claimOwner, $now, $lockUntil);
    }

    public function markDeliveryPublished(string $deliveryId, string $eventId): void
    {
        $now = (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $this->connection->update(self::DELIVERY_TABLE, [
            'status' => self::STATUS_PUBLISHED,
            'published_at' => $now,
            'locked_until' => null,
            'lock_owner' => null,
            'last_error' => null,
            'updated_at' => $now,
        ], ['id' => $deliveryId]);

        $this->syncEventStatus($eventId);
    }

    public function rescheduleDelivery(
        string $deliveryId,
        string $eventId,
        int $attempts,
        \DateTimeImmutable $availableAt,
        string $errorMessage
    ): void {
        $now = (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $this->connection->update(self::DELIVERY_TABLE, [
            'status' => self::STATUS_FAILED,
            'attempts' => $attempts,
            'available_at' => $availableAt->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'locked_until' => null,
            'lock_owner' => null,
            'last_error' => mb_substr($errorMessage, 0, 65000),
            'updated_at' => $now,
        ], ['id' => $deliveryId]);

        $this->syncEventStatus($eventId);
    }

    public function markDeliveryDead(
        string $deliveryId,
        string $eventId,
        int $attempts,
        string $errorMessage
    ): void {
        $now = (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $this->connection->update(self::DELIVERY_TABLE, [
            'status' => self::STATUS_DEAD,
            'attempts' => $attempts,
            'locked_until' => null,
            'lock_owner' => null,
            'last_error' => mb_substr($errorMessage, 0, 65000),
            'updated_at' => $now,
        ], ['id' => $deliveryId]);

        $this->syncEventStatus($eventId);
    }

    public function resetExpiredProcessingLocks(): int
    {
        $eventIds = $this->connection->fetchFirstColumn(<<<SQL
            SELECT DISTINCT `event_id`
            FROM `fib_outbox_delivery`
            WHERE `status` = :processing
              AND `locked_until` IS NOT NULL
              AND `locked_until` < :now
        SQL, [
            'processing' => self::STATUS_PROCESSING,
            'now' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        if ($eventIds === []) {
            return 0;
        }

        $now = (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $updated = $this->connection->executeStatement(<<<SQL
            UPDATE `fib_outbox_delivery`
            SET
                `status` = :pending,
                `locked_until` = NULL,
                `lock_owner` = NULL,
                `updated_at` = :now
            WHERE `status` = :processing
              AND `locked_until` IS NOT NULL
              AND `locked_until` < :now
        SQL, [
            'pending' => self::STATUS_PENDING,
            'processing' => self::STATUS_PROCESSING,
            'now' => $now,
        ]);

        foreach ($eventIds as $eventId) {
            $eventId = (string) $eventId;
            if ($eventId === '') {
                continue;
            }

            $this->syncEventStatus($eventId);
        }

        return $updated;
    }

    public function requeueDead(int $limit = 100, ?string $eventName = null): int
    {
        $limit = max(1, min(1000, $limit));

        $whereEvent = '';
        $params = [
            'dead' => self::STATUS_DEAD,
        ];

        if (!empty($eventName)) {
            $whereEvent = ' AND e.`event_name` = :eventName';
            $params['eventName'] = $eventName;
        }

        $rows = $this->connection->fetchAllAssociative(<<<SQL
            SELECT d.`id` AS delivery_id, d.`event_id`
            FROM `fib_outbox_delivery` d
            INNER JOIN `fib_outbox_event` e ON e.`id` = d.`event_id`
            WHERE d.`status` = :dead{$whereEvent}
            ORDER BY d.`updated_at` DESC, d.`id` DESC
            LIMIT {$limit}
        SQL, $params);

        if ($rows === []) {
            return 0;
        }

        $deliveryIds = [];
        $eventIds = [];

        foreach ($rows as $row) {
            $deliveryId = (string) ($row['delivery_id'] ?? '');
            $eventId = (string) ($row['event_id'] ?? '');

            if ($deliveryId === '' || $eventId === '') {
                continue;
            }

            $deliveryIds[] = $deliveryId;
            $eventIds[$eventId] = true;
        }

        if ($deliveryIds === []) {
            return 0;
        }

        $now = (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $updated = $this->connection->executeStatement(<<<SQL
            UPDATE `fib_outbox_delivery`
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
            'now' => $now,
            'ids' => $deliveryIds,
        ], [
            'ids' => ArrayParameterType::STRING,
        ]);

        foreach (array_keys($eventIds) as $eventId) {
            $this->syncEventStatus($eventId);
        }

        return $updated;
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
            if (empty($row['status'])) {
                continue;
            }

            $status = (string) $row['status'];
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

        if ($value === false || $value === null) {
            return null;
        }

        return max(0, (int) $value);
    }

    public function seedMissingDeliveries(int $limit = 200): int
    {
        $limit = max(1, min(5000, $limit));

        $rows = $this->connection->fetchAllAssociative(<<<SQL
            SELECT e.*
            FROM `fib_outbox_event` e
            WHERE e.`status` IN (:statuses)
              AND NOT EXISTS (
                  SELECT 1
                  FROM `fib_outbox_delivery` d
                  WHERE d.`event_id` = e.`id`
              )
            ORDER BY e.`created_at` ASC
            LIMIT {$limit}
        SQL, [
            'statuses' => [self::STATUS_PENDING, self::STATUS_PROCESSING, self::STATUS_FAILED],
        ], [
            'statuses' => ArrayParameterType::STRING,
        ]);

        if ($rows === []) {
            return 0;
        }

        $now = new \DateTimeImmutable();
        $count = 0;

        foreach ($rows as $row) {
            $event = DomainEvent::fromOutboxRow($row);
            $this->createDeliveriesForEvent($event, $now);
            ++$count;
        }

        return $count;
    }

    /**
     * @param list<array{id: string, key: string, type: string, config: array<string, mixed>}> $targets
     */
    private function insertDeliveries(string $eventId, array $targets, \DateTimeImmutable $now): void
    {
        foreach ($targets as $target) {
            $destinationId = (string) ($target['id'] ?? '');
            $targetKey = (string) ($target['key'] ?? '');
            $targetType = (string) ($target['type'] ?? '');
            $targetConfig = $target['config'] ?? [];
            if ($targetConfig !== (array) $targetConfig) {
                $targetConfig = [];
            }

            if ($destinationId === '' || $targetKey === '' || $targetType === '') {
                continue;
            }

            $this->connection->insert(self::DELIVERY_TABLE, [
                'id' => Uuid::randomHex(),
                'event_id' => $eventId,
                'target_key' => $targetKey,
                'target_type' => $targetType,
                'target_config' => json_encode($targetConfig, \JSON_THROW_ON_ERROR),
                'status' => self::STATUS_PENDING,
                'attempts' => 0,
                'available_at' => $now->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'published_at' => null,
                'locked_until' => null,
                'lock_owner' => null,
                'last_error' => null,
                'created_at' => $now->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'updated_at' => $now->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ]);

            if ($this->hasDeliveryDestinationIdColumn()) {
                $this->connection->update(self::DELIVERY_TABLE, [
                    'destination_id' => $destinationId,
                ], [
                    'event_id' => $eventId,
                    'target_key' => $targetKey,
                ]);
            }
        }
    }

    private function createDeliveriesForEvent(DomainEvent $event, \DateTimeImmutable $now): void
    {
        $targets = $this->routeResolver->resolveTargetsForEventName($event->getEventName());

        if ($targets === []) {
            $this->connection->update(self::TABLE, [
                'status' => self::STATUS_PUBLISHED,
                'published_at' => $now->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'updated_at' => $now->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            ], [
                'id' => $event->getId(),
            ]);

            return;
        }

        $this->insertDeliveries($event->getId(), $targets, $now);
        $this->syncEventStatus($event->getId());
    }

    private function syncEventStatus(string $eventId): void
    {
        $rows = $this->connection->fetchAllAssociative(<<<SQL
            SELECT `status`, COUNT(*) AS `cnt`
            FROM `fib_outbox_delivery`
            WHERE `event_id` = :eventId
            GROUP BY `status`
        SQL, [
            'eventId' => $eventId,
        ]);

        $deliveryMeta = $this->connection->fetchAssociative(<<<SQL
            SELECT
                COALESCE(MAX(`attempts`), 0) AS `max_attempts`
            FROM `fib_outbox_delivery`
            WHERE `event_id` = :eventId
        SQL, [
            'eventId' => $eventId,
        ]);

        $latestError = $this->connection->fetchOne(<<<SQL
            SELECT `last_error`
            FROM `fib_outbox_delivery`
            WHERE `event_id` = :eventId
              AND `last_error` IS NOT NULL
              AND `last_error` <> ''
            ORDER BY `updated_at` DESC, `id` DESC
            LIMIT 1
        SQL, [
            'eventId' => $eventId,
        ]);

        $counts = [
            self::STATUS_PENDING => 0,
            self::STATUS_FAILED => 0,
            self::STATUS_PROCESSING => 0,
            self::STATUS_PUBLISHED => 0,
            self::STATUS_DEAD => 0,
        ];

        $total = 0;

        foreach ($rows as $row) {
            if (empty($row['status'])) {
                continue;
            }

            $status = (string) $row['status'];
            $count = (int) ($row['cnt'] ?? 0);
            $total += $count;

            if (array_key_exists($status, $counts)) {
                $counts[$status] = $count;
            }
        }

        $maxAttempts = (int) ($deliveryMeta['max_attempts'] ?? 0);
        $eventStatus = self::STATUS_PENDING;
        $publishedAt = null;

        if ($total === 0) {
            $eventStatus = self::STATUS_PUBLISHED;
            $publishedAt = (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        } elseif ($counts[self::STATUS_PROCESSING] > 0) {
            $eventStatus = self::STATUS_PROCESSING;
        } elseif ($counts[self::STATUS_FAILED] > 0) {
            $eventStatus = self::STATUS_FAILED;
        } elseif ($counts[self::STATUS_PENDING] > 0 && $maxAttempts > 0) {
            $eventStatus = self::STATUS_FAILED;
        } elseif ($counts[self::STATUS_PENDING] > 0) {
            $eventStatus = self::STATUS_PENDING;
        } elseif ($counts[self::STATUS_DEAD] > 0) {
            $eventStatus = self::STATUS_DEAD;
        } else {
            $eventStatus = self::STATUS_PUBLISHED;
            $publishedAt = (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT);
        }

        $this->connection->update(self::TABLE, [
            'status' => $eventStatus,
            'attempts' => $maxAttempts,
            'published_at' => $publishedAt,
            'last_error' => empty($latestError) ? null : mb_substr((string) $latestError, 0, 65000),
            'updated_at' => (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ], [
            'id' => $eventId,
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function claimDeliveryBatchOptimistic(
        int $limit,
        string $claimOwner,
        \DateTimeImmutable $now,
        \DateTimeImmutable $lockUntil
    ): array {
        $candidateIds = $this->connection->fetchFirstColumn(<<<SQL
            SELECT `id`
            FROM `fib_outbox_delivery`
            WHERE (`status` = :pending OR `status` = :failed)
              AND `available_at` <= :now
              AND (`locked_until` IS NULL OR `locked_until` < :now)
            ORDER BY `available_at` ASC, `created_at` ASC, `id` ASC
            LIMIT {$limit}
        SQL, [
            'pending' => self::STATUS_PENDING,
            'failed' => self::STATUS_FAILED,
            'now' => $now->format(Defaults::STORAGE_DATE_TIME_FORMAT),
        ]);

        if ($candidateIds === []) {
            return [];
        }

        $claimedIds = [];

        foreach ($candidateIds as $candidateId) {
            $candidateId = (string) $candidateId;
            if ($candidateId === '') {
                continue;
            }

            $updated = $this->connection->executeStatement(<<<SQL
                UPDATE `fib_outbox_delivery`
                SET
                    `status` = :processing,
                    `lock_owner` = :lockOwner,
                    `locked_until` = :lockedUntil,
                    `updated_at` = :updatedAt
                WHERE `id` = :id
                  AND (`status` = :pending OR `status` = :failed)
                  AND `available_at` <= :now
                  AND (`locked_until` IS NULL OR `locked_until` < :now)
            SQL, [
                'processing' => self::STATUS_PROCESSING,
                'lockOwner' => $claimOwner,
                'lockedUntil' => $lockUntil->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'updatedAt' => $now->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'pending' => self::STATUS_PENDING,
                'failed' => self::STATUS_FAILED,
                'now' => $now->format(Defaults::STORAGE_DATE_TIME_FORMAT),
                'id' => $candidateId,
            ]);

            if ($updated === 1) {
                $claimedIds[] = $candidateId;
            }

            if (count($claimedIds) >= $limit) {
                break;
            }
        }

        if ($claimedIds === []) {
            return [];
        }

        return $this->fetchDeliveryRowsByIds($claimedIds);
    }

    /**
     * @param list<string> $deliveryIds
     * @return list<array<string, mixed>>
     */
    private function fetchDeliveryRowsByIds(array $deliveryIds): array
    {
        if ($deliveryIds === []) {
            return [];
        }

        $destinationSelect = $this->hasDeliveryDestinationIdColumn()
            ? "d.`destination_id`,"
            : "NULL AS `destination_id`,";

        return $this->connection->fetchAllAssociative(<<<SQL
            SELECT
                d.`id` AS `delivery_id`,
                d.`event_id`,
                {$destinationSelect}
                d.`target_key`,
                d.`target_type`,
                d.`target_config`,
                d.`attempts` AS `delivery_attempts`,
                e.*
            FROM `fib_outbox_delivery` d
            INNER JOIN `fib_outbox_event` e ON e.`id` = d.`event_id`
            WHERE d.`id` IN (:ids)
            ORDER BY d.`available_at` ASC, d.`created_at` ASC, d.`id` ASC
        SQL, [
            'ids' => $deliveryIds,
        ], [
            'ids' => ArrayParameterType::STRING,
        ]);
    }

    private function hasDeliveryDestinationIdColumn(): bool
    {
        if ($this->deliveryHasDestinationIdColumn !== null) {
            return $this->deliveryHasDestinationIdColumn;
        }

        $this->deliveryHasDestinationIdColumn = (int) $this->connection->fetchOne(<<<SQL
            SELECT COUNT(*)
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
              AND table_name = :tableName
              AND column_name = :columnName
        SQL, [
            'tableName' => self::DELIVERY_TABLE,
            'columnName' => 'destination_id',
        ]) > 0;

        return $this->deliveryHasDestinationIdColumn;
    }
}

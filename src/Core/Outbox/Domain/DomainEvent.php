<?php declare(strict_types=1);

namespace Fib\OutboxBridge\Core\Outbox\Domain;

use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;

class DomainEvent
{
    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed>|null $meta
     */
    public function __construct(
        private readonly string $id,
        private readonly string $eventName,
        private readonly string $aggregateType,
        private readonly string $aggregateId,
        private readonly \DateTimeImmutable $occurredAt,
        private readonly array $payload,
        private readonly ?array $meta = null
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed>|null $meta
     */
    public static function create(
        string $eventName,
        string $aggregateType,
        string $aggregateId,
        array $payload,
        ?array $meta = null,
        ?string $eventId = null,
        ?\DateTimeImmutable $occurredAt = null
    ): self {
        return new self(
            $eventId ?? Uuid::randomHex(),
            $eventName,
            $aggregateType,
            $aggregateId,
            $occurredAt ?? new \DateTimeImmutable(),
            $payload,
            $meta
        );
    }

    /**
     * @param array<string, mixed> $row
     */
    public static function fromOutboxRow(array $row): self
    {
        $occurredAt = new \DateTimeImmutable((string) $row['occurred_at']);
        $payload = json_decode((string) $row['payload'], true);
        $meta = json_decode((string) ($row['meta'] ?? 'null'), true);

        return new self(
            (string) $row['id'],
            (string) $row['event_name'],
            (string) $row['aggregate_type'],
            (string) $row['aggregate_id'],
            $occurredAt,
            $payload === (array) $payload ? $payload : [],
            $meta === (array) $meta ? $meta : null
        );
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getEventName(): string
    {
        return $this->eventName;
    }

    public function getAggregateType(): string
    {
        return $this->aggregateType;
    }

    public function getAggregateId(): string
    {
        return $this->aggregateId;
    }

    public function getOccurredAt(): \DateTimeImmutable
    {
        return $this->occurredAt;
    }

    /**
     * @return array<string, mixed>
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getMeta(): ?array
    {
        return $this->meta;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'eventId' => $this->id,
            'eventName' => $this->eventName,
            'aggregateType' => $this->aggregateType,
            'aggregateId' => $this->aggregateId,
            'occurredAt' => $this->occurredAt->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            'payload' => $this->payload,
            'meta' => $this->meta,
        ];
    }
}

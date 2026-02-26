<?php declare(strict_types=1);

namespace Fib\OutboxBridge\Core\Outbox\Message;

class OutboundDomainEventMessage
{
    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed>|null $meta
     */
    public function __construct(
        private readonly string $eventId,
        private readonly string $eventName,
        private readonly string $aggregateType,
        private readonly string $aggregateId,
        private readonly string $occurredAt,
        private readonly array $payload,
        private readonly ?array $meta
    ) {
    }

    public function getEventId(): string
    {
        return $this->eventId;
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

    public function getOccurredAt(): string
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
}

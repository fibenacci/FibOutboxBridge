<?php declare(strict_types=1);

namespace Fib\OutboxBridge\Core\Content\OutboxEvent;

use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class OutboxEventEntity extends Entity
{
    use EntityIdTrait;

    protected string $eventName;

    protected string $aggregateType;

    protected string $aggregateId;

    /**
     * @var array<string, mixed>
     */
    protected array $payload = [];

    /**
     * @var array<string, mixed>|null
     */
    protected ?array $meta = null;

    protected \DateTimeInterface $occurredAt;

    protected \DateTimeInterface $availableAt;

    protected ?\DateTimeInterface $publishedAt = null;

    protected string $status;

    protected int $attempts = 0;

    protected ?\DateTimeInterface $lockedUntil = null;

    protected ?string $lockOwner = null;

    protected ?string $lastError = null;

    public function getEventName(): string
    {
        return $this->eventName;
    }

    public function setEventName(string $eventName): void
    {
        $this->eventName = $eventName;
    }

    public function getAggregateType(): string
    {
        return $this->aggregateType;
    }

    public function setAggregateType(string $aggregateType): void
    {
        $this->aggregateType = $aggregateType;
    }

    public function getAggregateId(): string
    {
        return $this->aggregateId;
    }

    public function setAggregateId(string $aggregateId): void
    {
        $this->aggregateId = $aggregateId;
    }

    /**
     * @return array<string, mixed>
     */
    public function getPayload(): array
    {
        return $this->payload;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function setPayload(array $payload): void
    {
        $this->payload = $payload;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getMeta(): ?array
    {
        return $this->meta;
    }

    /**
     * @param array<string, mixed>|null $meta
     */
    public function setMeta(?array $meta): void
    {
        $this->meta = $meta;
    }

    public function getOccurredAt(): \DateTimeInterface
    {
        return $this->occurredAt;
    }

    public function setOccurredAt(\DateTimeInterface $occurredAt): void
    {
        $this->occurredAt = $occurredAt;
    }

    public function getAvailableAt(): \DateTimeInterface
    {
        return $this->availableAt;
    }

    public function setAvailableAt(\DateTimeInterface $availableAt): void
    {
        $this->availableAt = $availableAt;
    }

    public function getPublishedAt(): ?\DateTimeInterface
    {
        return $this->publishedAt;
    }

    public function setPublishedAt(?\DateTimeInterface $publishedAt): void
    {
        $this->publishedAt = $publishedAt;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function getAttempts(): int
    {
        return $this->attempts;
    }

    public function setAttempts(int $attempts): void
    {
        $this->attempts = $attempts;
    }

    public function getLockedUntil(): ?\DateTimeInterface
    {
        return $this->lockedUntil;
    }

    public function setLockedUntil(?\DateTimeInterface $lockedUntil): void
    {
        $this->lockedUntil = $lockedUntil;
    }

    public function getLockOwner(): ?string
    {
        return $this->lockOwner;
    }

    public function setLockOwner(?string $lockOwner): void
    {
        $this->lockOwner = $lockOwner;
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    public function setLastError(?string $lastError): void
    {
        $this->lastError = $lastError;
    }
}

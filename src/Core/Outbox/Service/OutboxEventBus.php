<?php declare(strict_types=1);

namespace Fib\OutboxBridge\Core\Outbox\Service;

use Doctrine\DBAL\Connection;
use Fib\OutboxBridge\Core\Outbox\Domain\DomainEvent;
use Fib\OutboxBridge\Core\Outbox\Repository\OutboxRepository;

class OutboxEventBus
{
    /**
     * @var list<DomainEvent>
     */
    private array $recordedEvents = [];

    public function __construct(
        private readonly OutboxRepository $outboxRepository,
        private readonly Connection $connection
    ) {
    }

    public function record(DomainEvent $event): void
    {
        $this->recordedEvents[] = $event;
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed>|null $meta
     */
    public function recordNamed(
        string $eventName,
        string $aggregateType,
        string $aggregateId,
        array $payload,
        ?array $meta = null
    ): DomainEvent {
        $event = DomainEvent::create(
            $eventName,
            $aggregateType,
            $aggregateId,
            $payload,
            $meta
        );

        $this->record($event);

        return $event;
    }

    public function flush(): int
    {
        $events = $this->recordedEvents;
        $this->recordedEvents = [];

        try {
            return $this->outboxRepository->appendMany($events);
        } catch (\Throwable $exception) {
            $this->recordedEvents = array_merge($events, $this->recordedEvents);

            throw $exception;
        }
    }

    /**
     * Executes domain work + outbox flush in one DB transaction.
     *
     * @template T
     * @param callable(self):T $callback
     * @return T
     */
    public function transactional(callable $callback): mixed
    {
        return $this->connection->transactional(function () use ($callback) {
            $result = $callback($this);
            $this->flush();

            return $result;
        });
    }
}

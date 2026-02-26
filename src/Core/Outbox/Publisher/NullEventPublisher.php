<?php declare(strict_types=1);

namespace Fib\OutboxBridge\Core\Outbox\Publisher;

use Fib\OutboxBridge\Core\Outbox\Domain\DomainEvent;
use Psr\Log\LoggerInterface;

class NullEventPublisher implements EventPublisherInterface
{
    public function __construct(
        private readonly LoggerInterface $logger
    ) {
    }

    public function publish(DomainEvent $event): void
    {
        $this->logger->info('Outbox event dropped by null publisher.', [
            'eventId' => $event->getId(),
            'eventName' => $event->getEventName(),
        ]);
    }
}

<?php declare(strict_types=1);

namespace Fib\OutboxBridge\Core\Outbox\Destination\Strategy;

use Fib\OutboxBridge\Core\Outbox\Destination\OutboxDestinationStrategyInterface;
use Fib\OutboxBridge\Core\Outbox\Domain\DomainEvent;
use Psr\Log\LoggerInterface;

class NullOutboxDestinationStrategy implements OutboxDestinationStrategyInterface
{
    public function __construct(
        private readonly LoggerInterface $logger
    ) {
    }

    public function getType(): string
    {
        return 'null';
    }

    public function getLabel(): string
    {
        return 'Null (drop)';
    }

    public function getConfigFields(): array
    {
        return [];
    }

    public function validateConfig(array $config): void
    {
    }

    public function publish(DomainEvent $event, array $context, array $config): void
    {
        $this->logger->info('Outbox event dropped by null destination.', [
            'eventId' => $event->getId(),
            'eventName' => $event->getEventName(),
            'destinationId' => $context['id'],
            'destinationKey' => $context['key'],
            'deliveryId' => $context['deliveryId'],
        ]);
    }
}

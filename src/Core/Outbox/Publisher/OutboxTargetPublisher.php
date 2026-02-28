<?php declare(strict_types=1);

namespace Fib\OutboxBridge\Core\Outbox\Publisher;

use Fib\OutboxBridge\Core\Outbox\Destination\OutboxDestinationStrategyRegistry;
use Fib\OutboxBridge\Core\Outbox\Domain\DomainEvent;

class OutboxTargetPublisher
{
    public function __construct(
        private readonly OutboxDestinationStrategyRegistry $strategyRegistry
    ) {
    }

    /**
     * @param array{id: string, key: string, type: string, config: array<string, mixed>} $target
     * @param array{deliveryId?: string}|null $deliveryContext
     */
    public function publish(DomainEvent $event, array $target, ?array $deliveryContext = null): void
    {
        $type = strtolower((string) ($target['type'] ?? ''));
        if ($type === '') {
            throw new \RuntimeException('Outbox delivery has empty destination type.');
        }

        $strategy = $this->strategyRegistry->getByType($type);
        if ($strategy === null) {
            throw new \RuntimeException(sprintf('No outbox destination strategy registered for type "%s".', $type));
        }

        $config = is_array($target['config'] ?? null) ? $target['config'] : [];
        $context = [
            'id' => (string) ($target['id'] ?? ''),
            'key' => (string) ($target['key'] ?? ''),
            'deliveryId' => trim((string) ($deliveryContext['deliveryId'] ?? '')),
        ];

        $strategy->validateConfig($config);
        $strategy->publish($event, $context, $config);
    }
}

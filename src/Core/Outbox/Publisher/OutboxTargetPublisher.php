<?php declare(strict_types=1);

namespace Fib\OutboxBridge\Core\Outbox\Publisher;

use Fib\OutboxBridge\Core\Outbox\Destination\OutboxDestinationStrategyRegistry;
use Fib\OutboxBridge\Core\Outbox\Domain\DomainEvent;
use Fib\OutboxBridge\Core\Outbox\Security\OutboxCredentialResolverInterface;

class OutboxTargetPublisher
{
    public function __construct(
        private readonly OutboxDestinationStrategyRegistry $strategyRegistry,
        private readonly OutboxCredentialResolverInterface $credentialResolver
    ) {
    }

    /**
     * @param array{id: string, key: string, type: string, config: array<string, mixed>} $target
     * @param array{deliveryId?: string}|null $deliveryContext
     */
    public function publish(DomainEvent $event, array $target, ?array $deliveryContext = null): void
    {
        if (empty($target['type'])) {
            throw new \RuntimeException('Outbox delivery has empty destination type.');
        }

        $type = (string) $target['type'];

        $strategy = $this->strategyRegistry->getByType($type);
        if ($strategy === null) {
            throw new \RuntimeException(sprintf('No outbox destination strategy registered for type "%s".', $type));
        }

        $config = $target['config'] ?? [];
        if ($config !== (array) $config) {
            $config = [];
        }
        $deliveryId = '';
        if ($deliveryContext === (array) $deliveryContext && !empty($deliveryContext['deliveryId'])) {
            $deliveryId = (string) $deliveryContext['deliveryId'];
        }

        $context = [
            'id' => (string) ($target['id'] ?? ''),
            'key' => (string) ($target['key'] ?? ''),
            'deliveryId' => $deliveryId,
        ];

        $resolvedConfig = $this->credentialResolver->resolveConfig($config);

        $strategy->validateConfig($resolvedConfig);
        $strategy->publish($event, $context, $resolvedConfig);
    }
}

<?php declare(strict_types=1);

namespace Fib\OutboxBridge\Core\Outbox\Destination;

use Fib\OutboxBridge\Core\Outbox\Domain\DomainEvent;

interface OutboxDestinationStrategyInterface
{
    public function getType(): string;

    public function getLabel(): string;

    /**
     * @return list<array{
     *   name: string,
     *   type: string,
     *   label: string,
     *   required?: bool,
     *   placeholder?: string,
     *   default?: scalar|null
     * }>
     */
    public function getConfigFields(): array;

    /**
     * @param array<string, mixed> $config
     */
    public function validateConfig(array $config): void;

    /**
     * @param array{id: string, key: string, deliveryId: string} $context
     * @param array<string, mixed> $config
     */
    public function publish(DomainEvent $event, array $context, array $config): void;
}

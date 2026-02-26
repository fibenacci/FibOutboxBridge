<?php declare(strict_types=1);

namespace Fib\OutboxBridge\Core\Outbox\Publisher;

use Fib\OutboxBridge\Core\Outbox\Domain\DomainEvent;

interface EventPublisherInterface
{
    public function publish(DomainEvent $event): void;
}

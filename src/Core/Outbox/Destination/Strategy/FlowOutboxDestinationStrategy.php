<?php

declare(strict_types=1);

namespace Fib\OutboxBridge\Core\Outbox\Destination\Strategy;

use Fib\OutboxBridge\Core\Outbox\Destination\OutboxDestinationStrategyInterface;
use Fib\OutboxBridge\Core\Outbox\Domain\DomainEvent;
use Fib\OutboxBridge\Core\Outbox\Flow\OutboxFlowForwardedEvent;
use Shopware\Core\Framework\Context;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class FlowOutboxDestinationStrategy implements OutboxDestinationStrategyInterface
{
    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function getType(): string
    {
        return 'flow';
    }

    public function getLabel(): string
    {
        return 'Flow event';
    }

    public function getConfigFields(): array
    {
        return [
            [
                'name'        => 'flowEventName',
                'type'        => 'text',
                'label'       => 'Flow event name',
                'required'    => false,
                'default'     => OutboxFlowForwardedEvent::DEFAULT_EVENT_NAME,
                'placeholder' => OutboxFlowForwardedEvent::DEFAULT_EVENT_NAME,
            ],
        ];
    }

    public function validateConfig(array $config): void
    {
    }

    public function publish(DomainEvent $event, array $context, array $config): void
    {
        $flowEventName = (string) ($config['flowEventName'] ?? OutboxFlowForwardedEvent::DEFAULT_EVENT_NAME);

        if ($flowEventName === '') {
            $flowEventName = OutboxFlowForwardedEvent::DEFAULT_EVENT_NAME;
        }

        $flowEvent = new OutboxFlowForwardedEvent(
            $flowEventName,
            Context::createDefaultContext(),
            $event,
            $context['id'],
            $context['key'],
            $context['deliveryId'],
            $config
        );

        $this->eventDispatcher->dispatch($flowEvent, $flowEventName);
    }
}

<?php

declare(strict_types=1);

namespace Fib\OutboxBridge\Core\Outbox\Destination\Strategy;

use Fib\OutboxBridge\Core\Outbox\Destination\OutboxDestinationStrategyInterface;
use Fib\OutboxBridge\Core\Outbox\Domain\DomainEvent;
use Fib\OutboxBridge\Core\Outbox\Message\OutboundDomainEventMessage;
use Shopware\Core\Defaults;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

class MessengerOutboxDestinationStrategy implements OutboxDestinationStrategyInterface
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    public function getType(): string
    {
        return 'messenger';
    }

    public function getLabel(): string
    {
        return 'Messenger';
    }

    public function getConfigFields(): array
    {
        return [
            [
                'name'        => 'routingKey',
                'type'        => 'text',
                'label'       => 'Routing key',
                'required'    => false,
                'placeholder' => 'Leave empty to use event name',
            ],
        ];
    }

    public function validateConfig(array $config): void
    {
    }

    public function publish(DomainEvent $event, array $context, array $config): void
    {
        $routingKey = !empty($config['routingKey'])
            ? (string) $config['routingKey']
            : $event->getEventName();

        $message = new OutboundDomainEventMessage(
            $event->getId(),
            $event->getEventName(),
            $event->getAggregateType(),
            $event->getAggregateId(),
            $event->getOccurredAt()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            $event->getPayload(),
            $event->getMeta()
        );

        $envelope       = new Envelope($message);
        $amqpStampClass = 'Symfony\\Component\\Messenger\\Bridge\\Amqp\\Transport\\AmqpStamp';

        if (class_exists($amqpStampClass)) {
            $envelope = $envelope->with(new $amqpStampClass($routingKey));
        }

        $this->messageBus->dispatch($envelope);
    }
}

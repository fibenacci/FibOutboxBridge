<?php declare(strict_types=1);

namespace Fib\OutboxBridge\Core\Outbox\Publisher;

use Fib\OutboxBridge\Core\Outbox\Domain\DomainEvent;
use Fib\OutboxBridge\Core\Outbox\Message\OutboundDomainEventMessage;
use Shopware\Core\Defaults;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class MessengerEventPublisher implements EventPublisherInterface
{
    public function __construct(
        private readonly MessageBusInterface $messageBus
    ) {
    }

    public function publish(DomainEvent $event): void
    {
        $message = new OutboundDomainEventMessage(
            $event->getId(),
            $event->getEventName(),
            $event->getAggregateType(),
            $event->getAggregateId(),
            $event->getOccurredAt()->format(Defaults::STORAGE_DATE_TIME_FORMAT),
            $event->getPayload(),
            $event->getMeta()
        );

        $envelope = new Envelope($message);
        $amqpStampClass = 'Symfony\Component\Messenger\Bridge\Amqp\Transport\AmqpStamp';

        if (class_exists($amqpStampClass)) {
            $envelope = $envelope->with(new $amqpStampClass($event->getEventName()));
        }

        try {
            $this->messageBus->dispatch($envelope);
        } catch (ExceptionInterface $e) {

        }
    }
}

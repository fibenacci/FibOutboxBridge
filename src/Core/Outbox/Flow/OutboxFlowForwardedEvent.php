<?php

declare(strict_types=1);

namespace Fib\OutboxBridge\Core\Outbox\Flow;

use Fib\OutboxBridge\Core\Outbox\Domain\DomainEvent;
use Shopware\Core\Content\Flow\Dispatching\Aware\ScalarValuesAware;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Event\EventData\EventDataCollection;
use Shopware\Core\Framework\Event\EventData\ScalarValueType;
use Shopware\Core\Framework\Event\FlowEventAware;
use Symfony\Contracts\EventDispatcher\Event;

class OutboxFlowForwardedEvent extends Event implements FlowEventAware, ScalarValuesAware
{
    public const DEFAULT_EVENT_NAME = 'fib.outbox.forwarded';

    /**
     * @param array<string, mixed> $targetConfig
     */
    public function __construct(
        private readonly string $eventName,
        private readonly Context $context,
        private readonly DomainEvent $domainEvent,
        private readonly string $destinationId,
        private readonly string $destinationKey,
        private readonly string $deliveryId,
        private readonly array $targetConfig,
    ) {
    }

    public function getName(): string
    {
        return $this->eventName;
    }

    public function getContext(): Context
    {
        return $this->context;
    }

    /**
     * @return array<string, null|array<mixed>|scalar>
     */
    public function getValues(): array
    {
        return [
            'eventId'          => $this->domainEvent->getId(),
            'eventName'        => $this->domainEvent->getEventName(),
            'aggregateType'    => $this->domainEvent->getAggregateType(),
            'aggregateId'      => $this->domainEvent->getAggregateId(),
            'occurredAt'       => $this->domainEvent->getOccurredAt()->format(\DATE_ATOM),
            'payloadJson'      => json_encode($this->domainEvent->getPayload(), \JSON_THROW_ON_ERROR),
            'metaJson'         => json_encode($this->domainEvent->getMeta(), \JSON_THROW_ON_ERROR),
            'destinationId'    => $this->destinationId,
            'destinationKey'   => $this->destinationKey,
            'deliveryId'       => $this->deliveryId,
            'targetConfigJson' => json_encode($this->targetConfig, \JSON_THROW_ON_ERROR),
        ];
    }

    public static function getAvailableData(): EventDataCollection
    {
        return (new EventDataCollection())
            ->add('eventId', new ScalarValueType(ScalarValueType::TYPE_STRING))
            ->add('eventName', new ScalarValueType(ScalarValueType::TYPE_STRING))
            ->add('aggregateType', new ScalarValueType(ScalarValueType::TYPE_STRING))
            ->add('aggregateId', new ScalarValueType(ScalarValueType::TYPE_STRING))
            ->add('occurredAt', new ScalarValueType(ScalarValueType::TYPE_STRING))
            ->add('payloadJson', new ScalarValueType(ScalarValueType::TYPE_STRING))
            ->add('metaJson', new ScalarValueType(ScalarValueType::TYPE_STRING))
            ->add('destinationId', new ScalarValueType(ScalarValueType::TYPE_STRING))
            ->add('destinationKey', new ScalarValueType(ScalarValueType::TYPE_STRING))
            ->add('deliveryId', new ScalarValueType(ScalarValueType::TYPE_STRING))
            ->add('targetConfigJson', new ScalarValueType(ScalarValueType::TYPE_STRING));
    }
}

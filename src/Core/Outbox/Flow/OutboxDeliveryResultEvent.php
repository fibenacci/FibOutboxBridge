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

class OutboxDeliveryResultEvent extends Event implements FlowEventAware, ScalarValuesAware
{
    public const EVENT_NAME = 'fib.outbox.delivery.result';

    public const EVENT_NAME_SUCCEEDED = 'fib.outbox.delivery.succeeded';

    public const EVENT_NAME_FAILED = 'fib.outbox.delivery.failed';

    /**
     * @param array<string, mixed> $targetConfig
     */
    public function __construct(
        private readonly Context $context,
        private readonly DomainEvent $domainEvent,
        private readonly string $deliveryId,
        private readonly string $destinationId,
        private readonly string $destinationKey,
        private readonly string $destinationType,
        private readonly array $targetConfig,
        private readonly int $attempt,
        private readonly string $deliveryStatus,
        private readonly ?string $errorMessage,
    ) {
    }

    public function getName(): string
    {
        return self::EVENT_NAME;
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
            'deliveryId'       => $this->deliveryId,
            'destinationId'    => $this->destinationId,
            'destinationKey'   => $this->destinationKey,
            'destinationType'  => $this->destinationType,
            'targetConfigJson' => json_encode($this->targetConfig, \JSON_THROW_ON_ERROR),
            'attempt'          => $this->attempt,
            'deliveryStatus'   => $this->deliveryStatus,
            'errorMessage'     => $this->errorMessage,
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
            ->add('deliveryId', new ScalarValueType(ScalarValueType::TYPE_STRING))
            ->add('destinationId', new ScalarValueType(ScalarValueType::TYPE_STRING))
            ->add('destinationKey', new ScalarValueType(ScalarValueType::TYPE_STRING))
            ->add('destinationType', new ScalarValueType(ScalarValueType::TYPE_STRING))
            ->add('targetConfigJson', new ScalarValueType(ScalarValueType::TYPE_STRING))
            ->add('attempt', new ScalarValueType(ScalarValueType::TYPE_INT))
            ->add('deliveryStatus', new ScalarValueType(ScalarValueType::TYPE_STRING))
            ->add('errorMessage', new ScalarValueType(ScalarValueType::TYPE_STRING));
    }
}

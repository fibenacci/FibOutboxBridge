<?php declare(strict_types=1);

namespace Fib\OutboxBridge\Core\Flow\Service;

use Fib\OutboxBridge\Core\Outbox\Domain\DomainEvent;
use Fib\OutboxBridge\Core\Outbox\Repository\OutboxRepository;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;
use Shopware\Core\Framework\Uuid\Uuid;

class FlowOutboxEnqueueService
{
    public function __construct(
        private readonly OutboxDestinationSelector $destinationSelector,
        private readonly OutboxRepository $outboxRepository
    ) {
    }

    public function enqueueForDestinationType(
        StorableFlow $flow,
        string $destinationType,
        string $actionName
    ): int {
        $destinations = $this->destinationSelector->getActiveDestinationsByType($destinationType);

        if ($destinations === []) {
            return 0;
        }

        [$aggregateType, $aggregateId] = $this->resolveAggregate($flow);

        $meta = [
            'source' => $actionName,
            'flowName' => $flow->getName(),
            'flowSequenceId' => $this->resolveSequenceId($flow),
            'destinationType' => $destinationType,
            'destinationCount' => count($destinations),
            'contextVersionId' => $flow->getContext()->getVersionId(),
        ];

        $payload = [
            'flowName' => $flow->getName(),
            'actionName' => $actionName,
            'destinationType' => $destinationType,
            'store' => $this->normalizeValue($flow->stored()),
            'data' => $this->normalizeValue($flow->data()),
        ];

        $event = DomainEvent::create(
            sprintf('fib.outbox.enqueue.%s.v1', $destinationType),
            $aggregateType,
            $aggregateId,
            $payload,
            $meta
        );

        $this->outboxRepository->appendWithDestinations($event, $destinations);

        return count($destinations);
    }

    public function enqueueForConfiguredDestination(StorableFlow $flow, string $actionName): bool
    {
        $config = $flow->getConfig();
        if (empty($config['destinationId'])) {
            return false;
        }

        $destinationId = (string) $config['destinationId'];
        $destinationType = (string) ($config['destinationType'] ?? '');
        $sourceEventName = $flow->getName();

        $destination = $this->destinationSelector->getActiveDestinationById(
            $destinationId,
            $destinationType !== '' ? $destinationType : null
        );

        if ($destination === null) {
            return false;
        }

        [$aggregateType, $aggregateId] = $this->resolveAggregate($flow);

        $meta = [
            'source' => $actionName,
            'flowName' => $sourceEventName,
            'sourceEventName' => $sourceEventName,
            'flowSequenceId' => $this->resolveSequenceId($flow),
            'destinationType' => $destination['type'],
            'destinationId' => $destination['id'],
            'destinationKey' => $destination['key'],
            'contextVersionId' => $flow->getContext()->getVersionId(),
        ];

        $payload = [
            'flowName' => $sourceEventName,
            'sourceEventName' => $sourceEventName,
            'actionName' => $actionName,
            'destinationType' => $destination['type'],
            'destinationId' => $destination['id'],
            'destinationKey' => $destination['key'],
            'store' => $this->normalizeValue($flow->stored()),
            'data' => $this->normalizeValue($flow->data()),
        ];

        $event = DomainEvent::create(
            $sourceEventName !== '' ? $sourceEventName : 'fib.outbox.enqueue.destination.v1',
            $aggregateType,
            $aggregateId,
            $payload,
            $meta
        );

        $this->outboxRepository->appendWithDestinations($event, [$destination]);

        return true;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function resolveAggregate(StorableFlow $flow): array
    {
        $mapping = [
            'orderId' => 'order',
            'customerId' => 'customer',
            'productId' => 'product',
            'promotionId' => 'promotion',
            'salesChannelId' => 'sales_channel',
        ];

        foreach ($mapping as $key => $type) {
            $value = $flow->getStore($key);

            if (empty($value)) {
                continue;
            }

            return [$type, (string) $value];
        }

        $sequenceId = $this->resolveSequenceId($flow);
        if ($sequenceId !== null) {
            return ['flow_sequence', $sequenceId];
        }

        return ['flow_event', Uuid::randomHex()];
    }

    private function resolveSequenceId(StorableFlow $flow): ?string
    {
        try {
            return $flow->getFlowState()->getSequenceId();
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalizeValue(mixed $value): mixed
    {
        if ($value === null || $value === true || $value === false) {
            return $value;
        }

        if ($value instanceof \DateTimeInterface) {
            return $value->format(\DATE_ATOM);
        }

        if ($value === (array) $value) {
            $normalized = [];
            foreach ($value as $key => $item) {
                $normalized[(string) $key] = $this->normalizeValue($item);
            }

            return $normalized;
        }

        return (string) $value;
    }
}

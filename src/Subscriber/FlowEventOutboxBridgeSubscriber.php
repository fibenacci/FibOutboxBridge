<?php

declare(strict_types=1);

namespace Fib\OutboxBridge\Subscriber;

use Fib\OutboxBridge\Core\Outbox\Domain\DomainEvent;
use Fib\OutboxBridge\Core\Outbox\Repository\OutboxRepository;
use Fib\OutboxBridge\Core\Outbox\Routing\OutboxRouteResolver;
use Shopware\Core\Content\Flow\Dispatching\Aware\ScalarValuesAware;
use Shopware\Core\Framework\Event\FlowEventAware;
use Shopware\Core\Framework\Event\FlowLogEvent;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class FlowEventOutboxBridgeSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly OutboxRepository $repository,
        private readonly OutboxRouteResolver $routeResolver,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            FlowLogEvent::NAME => 'onFlowLog',
        ];
    }

    public function onFlowLog(FlowLogEvent $event): void
    {
        $sourceEvent     = $event->getEvent();
        $sourceEventName = $sourceEvent->getName();

        if ($sourceEventName === '') {
            return;
        }

        if (str_starts_with($sourceEventName, 'fib.outbox.')) {
            return;
        }

        if ($this->routeResolver->resolveTargetsForEventName($sourceEventName) === []) {
            return;
        }

        [$aggregateType, $aggregateId, $identifiers] = $this->resolveAggregate($sourceEvent);

        $payload = [
            'flowEventName'  => $sourceEventName,
            'flowEventClass' => $sourceEvent::class,
            'identifiers'    => $identifiers,
            'scalarValues'   => $this->extractScalarValues($sourceEvent),
        ];

        $meta = [
            'source'           => FlowLogEvent::NAME,
            'contextVersionId' => $sourceEvent->getContext()->getVersionId(),
        ];

        $this->repository->append(DomainEvent::create(
            $sourceEventName,
            $aggregateType,
            $aggregateId,
            $payload,
            $meta
        ));
    }

    /**
     * @return array{0: string, 1: string, 2: array<string, string>}
     */
    private function resolveAggregate(FlowEventAware $event): array
    {
        $candidates = [
            ['method' => 'getOrderId', 'type' => 'order'],
            ['method' => 'getCustomerId', 'type' => 'customer'],
            ['method' => 'getProductId', 'type' => 'product'],
            ['method' => 'getPromotionId', 'type' => 'promotion'],
            ['method' => 'getSalesChannelId', 'type' => 'sales_channel'],
        ];

        $identifiers = [];

        foreach ($candidates as $candidate) {
            $method = $candidate['method'];

            if (!method_exists($event, $method)) {
                continue;
            }

            $id = $event->{$method}();

            if (empty($id)) {
                continue;
            }

            $identifiers[$method] = $id;

            return [
                $candidate['type'],
                $id,
                $identifiers,
            ];
        }

        return [
            'flow_event',
            Uuid::randomHex(),
            $identifiers,
        ];
    }

    /**
     * @return array<string, null|array<mixed>|scalar>
     */
    private function extractScalarValues(FlowEventAware $event): array
    {
        if (!$event instanceof ScalarValuesAware) {
            return [];
        }

        return $event->getValues();
    }
}

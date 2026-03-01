<?php

declare(strict_types=1);

namespace Fib\OutboxBridge\Subscriber;

use Fib\OutboxBridge\Core\Outbox\Flow\OutboxDeliveryResultEvent;
use Fib\OutboxBridge\Core\Outbox\Flow\OutboxFlowForwardedEvent;
use Fib\OutboxBridge\Core\Outbox\Routing\OutboxRouteResolver;
use Shopware\Core\Framework\Event\BusinessEventCollector;
use Shopware\Core\Framework\Event\BusinessEventCollectorEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OutboxFlowBusinessEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly OutboxRouteResolver $routeResolver,
        private readonly BusinessEventCollector $businessEventCollector,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            BusinessEventCollectorEvent::NAME => 'onCollectBusinessEvents',
        ];
    }

    public function onCollectBusinessEvents(BusinessEventCollectorEvent $event): void
    {
        $collection = $event->getCollection();

        foreach ($this->routeResolver->getConfiguredFlowEventNames() as $flowEventName) {
            $definition = $this->businessEventCollector->define(OutboxFlowForwardedEvent::class, $flowEventName);

            if ($definition !== null) {
                $collection->set($flowEventName, $definition);
            }
        }

        $eventName  = OutboxDeliveryResultEvent::EVENT_NAME_FAILED;
        $definition = $this->businessEventCollector->define(OutboxDeliveryResultEvent::class, $eventName);

        if ($definition !== null) {
            $collection->set($eventName, $definition);
        }
    }
}

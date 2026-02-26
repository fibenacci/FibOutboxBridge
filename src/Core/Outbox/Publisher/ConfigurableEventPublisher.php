<?php declare(strict_types=1);

namespace Fib\OutboxBridge\Core\Outbox\Publisher;

use Fib\OutboxBridge\Core\Outbox\Config\OutboxSettings;
use Fib\OutboxBridge\Core\Outbox\Domain\DomainEvent;

class ConfigurableEventPublisher implements EventPublisherInterface
{
    public function __construct(
        private readonly OutboxSettings $settings,
        private readonly MessengerEventPublisher $messengerPublisher,
        private readonly WebhookEventPublisher $webhookPublisher,
        private readonly NullEventPublisher $nullPublisher
    ) {
    }

    public function publish(DomainEvent $event): void
    {
        $mode = $this->settings->getPublisherMode();

        if ($mode === 'webhook') {
            $this->webhookPublisher->publish($event);

            return;
        }

        if ($mode === 'null') {
            $this->nullPublisher->publish($event);

            return;
        }

        $this->messengerPublisher->publish($event);
    }
}

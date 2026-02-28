<?php declare(strict_types=1);

namespace Fib\OutboxBridge\Core\Outbox\Publisher;

use Fib\OutboxBridge\Core\Outbox\Domain\DomainEvent;
use Fib\OutboxBridge\Core\Outbox\Flow\OutboxFlowForwardedEvent;
use Fib\OutboxBridge\Core\Outbox\Message\OutboundDomainEventMessage;
use Psr\Log\LoggerInterface;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class OutboxTargetPublisher
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly HttpClientInterface $httpClient,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @param array{id: string, key: string, type: string, config: array<string, mixed>} $target
     * @param array{deliveryId?: string}|null $deliveryContext
     */
    public function publish(DomainEvent $event, array $target, ?array $deliveryContext = null): void
    {
        $type = strtolower((string) ($target['type'] ?? ''));
        $config = is_array($target['config'] ?? null) ? $target['config'] : [];
        $destinationId = (string) ($target['id'] ?? '');
        $destinationKey = (string) ($target['key'] ?? '');
        $deliveryId = trim((string) ($deliveryContext['deliveryId'] ?? ''));

        if ($type === 'webhook') {
            $this->publishWebhook($event, $destinationId, $destinationKey, $deliveryId, $config);

            return;
        }

        if ($type === 'flow') {
            $this->publishFlow($event, $destinationId, $destinationKey, $deliveryId, $config);

            return;
        }

        if ($type === 'null') {
            $this->logger->info('Outbox event dropped by null target.', [
                'eventId' => $event->getId(),
                'eventName' => $event->getEventName(),
                'targetId' => $destinationId,
                'targetKey' => $destinationKey,
                'deliveryId' => $deliveryId,
            ]);

            return;
        }

        $this->publishMessenger($event, $config);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function publishMessenger(DomainEvent $event, array $config): void
    {
        $routingKey = is_string($config['routingKey'] ?? null) && $config['routingKey'] !== ''
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

        $envelope = new Envelope($message);
        $amqpStampClass = 'Symfony\\Component\\Messenger\\Bridge\\Amqp\\Transport\\AmqpStamp';

        if (class_exists($amqpStampClass)) {
            $envelope = $envelope->with(new $amqpStampClass($routingKey));
        }

        $this->messageBus->dispatch($envelope);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function publishWebhook(
        DomainEvent $event,
        string $destinationId,
        string $destinationKey,
        string $deliveryId,
        array $config
    ): void {
        $url = trim((string) ($config['url'] ?? ''));
        if ($url === '') {
            throw new \RuntimeException(sprintf('Webhook target "%s" has empty URL.', $destinationKey));
        }

        $headers = [
            'Content-Type' => 'application/json',
            'X-Event-Id' => $event->getId(),
            'X-Event-Name' => $event->getEventName(),
            'X-Outbox-Destination-Id' => $destinationId,
            'X-Outbox-Destination-Key' => $destinationKey,
        ];

        if ($deliveryId !== '') {
            $headers['X-Outbox-Delivery-Id'] = $deliveryId;
        }

        if (is_array($config['headers'] ?? null)) {
            foreach ($config['headers'] as $headerName => $headerValue) {
                if (!is_string($headerName) || !is_scalar($headerValue)) {
                    continue;
                }

                $headers[$headerName] = (string) $headerValue;
            }
        }

        $response = $this->httpClient->request('POST', $url, [
            'headers' => $headers,
            'json' => $event->toArray(),
        ]);

        $statusCode = $response->getStatusCode();
        if ($statusCode < 200 || $statusCode >= 300) {
            throw new \RuntimeException(sprintf('Webhook publish failed with HTTP %d.', $statusCode));
        }
    }

    /**
     * @param array<string, mixed> $config
     */
    private function publishFlow(
        DomainEvent $event,
        string $destinationId,
        string $destinationKey,
        string $deliveryId,
        array $config
    ): void {
        $flowEventName = trim((string) ($config['flowEventName'] ?? OutboxFlowForwardedEvent::DEFAULT_EVENT_NAME));
        if ($flowEventName === '') {
            $flowEventName = OutboxFlowForwardedEvent::DEFAULT_EVENT_NAME;
        }

        $flowEvent = new OutboxFlowForwardedEvent(
            $flowEventName,
            Context::createDefaultContext(),
            $event,
            $destinationId,
            $destinationKey,
            $deliveryId,
            $config
        );

        $this->eventDispatcher->dispatch($flowEvent, $flowEventName);
    }
}

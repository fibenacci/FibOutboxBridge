<?php declare(strict_types=1);

namespace Fib\OutboxBridge\Core\Outbox\Destination\Strategy;

use Fib\OutboxBridge\Core\Outbox\Destination\OutboxDestinationStrategyInterface;
use Fib\OutboxBridge\Core\Outbox\Domain\DomainEvent;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;

class WebhookOutboxDestinationStrategy implements OutboxDestinationStrategyInterface
{
    public function __construct(
        private readonly ClientInterface $httpClient
    ) {
    }

    public function getType(): string
    {
        return 'webhook';
    }

    public function getLabel(): string
    {
        return 'Webhook';
    }

    public function getConfigFields(): array
    {
        return [
            [
                'name' => 'url',
                'type' => 'url',
                'label' => 'Webhook URL',
                'required' => true,
                'placeholder' => 'https://example.com/webhooks/orders',
            ],
        ];
    }

    public function validateConfig(array $config): void
    {
        if (empty($config['url'])) {
            throw new \RuntimeException('Webhook destination requires "url" config.');
        }
    }

    public function publish(DomainEvent $event, array $context, array $config): void
    {
        $this->validateConfig($config);

        $headers = [
            'Content-Type' => 'application/json',
            'X-Event-Id' => $event->getId(),
            'X-Event-Name' => $event->getEventName(),
            'X-Outbox-Destination-Id' => $context['id'],
            'X-Outbox-Destination-Key' => $context['key'],
        ];

        if ($context['deliveryId'] !== '') {
            $headers['X-Outbox-Delivery-Id'] = $context['deliveryId'];
        }

        if (($config['headers'] ?? null) === (array) ($config['headers'] ?? null)) {
            foreach ($config['headers'] as $headerName => $headerValue) {
                if ($headerValue === (array) $headerValue) {
                    continue;
                }

                $headers[(string) $headerName] = (string) $headerValue;
            }
        }

        try {
            $response = $this->httpClient->request('POST', $config['url'], [
                'headers' => $headers,
                'json' => $event->toArray(),
            ]);
        } catch (GuzzleException $e) {
            throw new \RuntimeException(sprintf('Webhook publish failed: %s', $e->getMessage()), 0, $e);
        }

        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            throw new \RuntimeException(sprintf('Webhook publish failed with HTTP %d.', $response->getStatusCode()));
        }
    }
}

<?php declare(strict_types=1);

namespace Fib\OutboxBridge\Core\Outbox\Destination\Strategy;

use Fib\OutboxBridge\Core\Outbox\Destination\OutboxDestinationStrategyInterface;
use Fib\OutboxBridge\Core\Outbox\Domain\DomainEvent;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class WebhookOutboxDestinationStrategy implements OutboxDestinationStrategyInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient
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
        $url = trim((string) ($config['url'] ?? ''));
        if ($url === '') {
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

        if (is_array($config['headers'] ?? null)) {
            foreach ($config['headers'] as $headerName => $headerValue) {
                if (!is_string($headerName) || !is_scalar($headerValue)) {
                    continue;
                }

                $headers[$headerName] = (string) $headerValue;
            }
        }

        $response = $this->httpClient->request('POST', (string) $config['url'], [
            'headers' => $headers,
            'json' => $event->toArray(),
        ]);

        $statusCode = $response->getStatusCode();
        if ($statusCode < 200 || $statusCode >= 300) {
            throw new \RuntimeException(sprintf('Webhook publish failed with HTTP %d.', $statusCode));
        }
    }
}

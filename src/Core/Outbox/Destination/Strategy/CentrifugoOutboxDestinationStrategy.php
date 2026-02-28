<?php declare(strict_types=1);

namespace Fib\OutboxBridge\Core\Outbox\Destination\Strategy;

use Fib\OutboxBridge\Core\Outbox\Destination\OutboxDestinationStrategyInterface;
use Fib\OutboxBridge\Core\Outbox\Domain\DomainEvent;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class CentrifugoOutboxDestinationStrategy implements OutboxDestinationStrategyInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient
    ) {
    }

    public function getType(): string
    {
        return 'centrifugo';
    }

    public function getLabel(): string
    {
        return 'Centrifugo';
    }

    public function getConfigFields(): array
    {
        return [
            [
                'name' => 'apiUrl',
                'type' => 'url',
                'label' => 'HTTP API URL',
                'required' => true,
                'placeholder' => 'http://centrifugo:8000/api',
            ],
            [
                'name' => 'apiKey',
                'type' => 'text',
                'label' => 'API key',
                'required' => true,
            ],
            [
                'name' => 'channel',
                'type' => 'text',
                'label' => 'Channel',
                'required' => true,
                'placeholder' => 'shopware.outbox.events',
            ],
        ];
    }

    public function validateConfig(array $config): void
    {
        $apiUrl = trim((string) ($config['apiUrl'] ?? ''));
        $apiKey = trim((string) ($config['apiKey'] ?? ''));
        $channel = trim((string) ($config['channel'] ?? ''));

        if ($apiUrl === '') {
            throw new \RuntimeException('Centrifugo destination requires "apiUrl" config.');
        }

        if ($apiKey === '') {
            throw new \RuntimeException('Centrifugo destination requires "apiKey" config.');
        }

        if ($channel === '') {
            throw new \RuntimeException('Centrifugo destination requires "channel" config.');
        }
    }

    public function publish(DomainEvent $event, array $context, array $config): void
    {
        $this->validateConfig($config);

        $response = $this->httpClient->request('POST', (string) $config['apiUrl'], [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => sprintf('apikey %s', (string) $config['apiKey']),
            ],
            'json' => [
                'method' => 'publish',
                'params' => [
                    'channel' => (string) $config['channel'],
                    'data' => [
                        'deliveryId' => $context['deliveryId'],
                        'destinationId' => $context['id'],
                        'destinationKey' => $context['key'],
                        'event' => $event->toArray(),
                    ],
                ],
            ],
        ]);

        $statusCode = $response->getStatusCode();
        if ($statusCode < 200 || $statusCode >= 300) {
            throw new \RuntimeException(sprintf('Centrifugo publish failed with HTTP %d.', $statusCode));
        }

        $payload = $response->toArray(false);
        if (is_array($payload) && array_key_exists('error', $payload) && !empty($payload['error'])) {
            $errorJson = json_encode($payload['error']);
            throw new \RuntimeException(sprintf('Centrifugo publish returned error: %s', $errorJson ?: 'unknown'));
        }
    }
}

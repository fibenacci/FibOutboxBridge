<?php declare(strict_types=1);

namespace Fib\OutboxBridge\Core\Outbox\Destination\Strategy;

use Fib\OutboxBridge\Core\Outbox\Destination\OutboxDestinationStrategyInterface;
use Fib\OutboxBridge\Core\Outbox\Domain\DomainEvent;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;

class CentrifugoOutboxDestinationStrategy implements OutboxDestinationStrategyInterface
{
    public function __construct(
        private readonly ClientInterface $httpClient
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
                'label' => 'API key (direct, avoid in production)',
                'required' => false,
            ],
            [
                'name' => 'apiKeyRef',
                'type' => 'text',
                'label' => 'API key reference (env:... or file:...)',
                'required' => false,
                'placeholder' => 'env:OUTBOX_CENTRIFUGO_API_KEY',
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
        if (empty($config['apiUrl'])) {
            throw new \RuntimeException('Centrifugo destination requires "apiUrl" config.');
        }

        if (empty($config['apiKey'])) {
            throw new \RuntimeException('Centrifugo destination requires "apiKey" or "apiKeyRef" config.');
        }

        if (empty($config['channel'])) {
            throw new \RuntimeException('Centrifugo destination requires "channel" config.');
        }
    }

    public function publish(DomainEvent $event, array $context, array $config): void
    {
        $this->validateConfig($config);

        try {
            $response = $this->httpClient->request('POST', $config['apiUrl'], [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Authorization' => sprintf('apikey %s', $config['apiKey']),
                ],
                'json' => [
                    'method' => 'publish',
                    'params' => [
                        'channel' => $config['channel'],
                        'data' => [
                            'deliveryId' => $context['deliveryId'],
                            'destinationId' => $context['id'],
                            'destinationKey' => $context['key'],
                            'event' => $event->toArray(),
                        ],
                    ],
                ],
            ]);
        } catch (GuzzleException $e) {
            throw new \RuntimeException(sprintf('Centrifugo publish failed: %s', $e->getMessage()), 0, $e);
        }

        if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
            throw new \RuntimeException(sprintf('Centrifugo publish failed with HTTP %d.', $response->getStatusCode()));
        }

        $payload = json_decode((string) $response->getBody(), true, 512, \JSON_THROW_ON_ERROR);
        if ($payload === (array) $payload && !empty($payload['error'])) {
            $errorJson = json_encode($payload['error']);
            throw new \RuntimeException(sprintf('Centrifugo publish returned error: %s', $errorJson ?: 'unknown'));
        }
    }
}

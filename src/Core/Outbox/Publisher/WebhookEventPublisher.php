<?php declare(strict_types=1);

namespace Fib\OutboxBridge\Core\Outbox\Publisher;

use Fib\OutboxBridge\Core\Outbox\Config\OutboxSettings;
use Fib\OutboxBridge\Core\Outbox\Domain\DomainEvent;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class WebhookEventPublisher implements EventPublisherInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly OutboxSettings $settings
    ) {
    }

    public function publish(DomainEvent $event): void
    {
        $url = $this->settings->getWebhookUrl();
        if (empty($url)) {
            throw new \RuntimeException('FibOutboxBridge webhookUrl is empty.');
        }

        $response = $this->httpClient->request('POST', $url, [
            'headers' => [
                'Content-Type' => 'application/json',
                'X-Event-Id' => $event->getId(),
                'X-Event-Name' => $event->getEventName(),
            ],
            'json' => $event->toArray(),
        ]);

        try {
            $statusCode = $response->getStatusCode();

            if ($statusCode < 200 || $statusCode >= 300) {
                throw new \RuntimeException(sprintf('Webhook publish failed with HTTP %d.', $statusCode));
            }
        } catch (TransportExceptionInterface $e) {

        }
    }
}

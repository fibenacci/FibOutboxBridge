<?php

declare(strict_types=1);

namespace Fib\OutboxBridge\Core\Outbox\Service;

use Fib\OutboxBridge\Core\Outbox\Config\OutboxSettings;
use Fib\OutboxBridge\Core\Outbox\Domain\DomainEvent;
use Fib\OutboxBridge\Core\Outbox\Flow\OutboxDeliveryResultEvent;
use Fib\OutboxBridge\Core\Outbox\Publisher\OutboxTargetPublisher;
use Fib\OutboxBridge\Core\Outbox\Repository\OutboxRepository;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\Context;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class OutboxDispatcher
{
    public function __construct(
        private readonly OutboxRepository $repository,
        private readonly OutboxTargetPublisher $targetPublisher,
        private readonly OutboxSettings $settings,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @return array<string, int>
     */
    public function dispatchBatch(?int $limit = null, ?string $workerId = null): array
    {
        $batchLimit = $limit ?? $this->settings->getDispatchBatchSize();
        $worker     = $workerId;

        if (empty($worker)) {
            $worker = sprintf('outbox-%s', bin2hex(random_bytes(6)));
        }

        $this->repository->seedMissingDeliveries($batchLimit);

        $rows = $this->repository->claimDeliveryBatch($batchLimit, $worker, $this->settings->getLockSeconds());

        $result = [
            'claimed'   => count($rows),
            'published' => 0,
            'retried'   => 0,
            'dead'      => 0,
            'errors'    => 0,
        ];

        foreach ($rows as $row) {
            $deliveryId = (string) ($row['delivery_id'] ?? '');
            $eventId    = (string) ($row['event_id'] ?? '');

            if ($deliveryId === '' || $eventId === '') {
                continue;
            }

            $target = $this->buildTargetFromRow($row);
            $event  = DomainEvent::fromOutboxRow($row);

            try {
                $this->targetPublisher->publish($event, $target, [
                    'deliveryId' => $deliveryId,
                ]);
                $this->repository->markDeliveryPublished($deliveryId, $eventId);
                ++$result['published'];
            } catch (\Throwable $exception) {
                ++$result['errors'];
                $attempts     = ((int) ($row['delivery_attempts'] ?? 0)) + 1;
                $errorMessage = $exception->getMessage();

                if ($attempts >= $this->settings->getMaxAttempts()) {
                    $this->repository->markDeliveryDead($deliveryId, $eventId, $attempts, $errorMessage);
                    ++$result['dead'];
                    $this->dispatchDeadDeliveryEvent($event, $target, $deliveryId, $attempts, $errorMessage);
                } else {
                    $this->repository->rescheduleDelivery(
                        $deliveryId,
                        $eventId,
                        $attempts,
                        (new \DateTimeImmutable())->modify(sprintf('+%d seconds', $this->getBackoffSeconds($attempts))),
                        $errorMessage
                    );
                    ++$result['retried'];
                }

                $this->logger->error('Outbox delivery publish failed.', [
                    'deliveryId' => $deliveryId,
                    'eventId'    => $eventId,
                    'targetId'   => $target['id'] ?? '',
                    'targetKey'  => $target['key'] ?? '',
                    'attempts'   => $attempts,
                    'error'      => $exception,
                ]);
            }
        }

        return $result;
    }

    private function getBackoffSeconds(int $attempts): int
    {
        $power   = max(0, min(10, $attempts - 1));
        $seconds = 60 * (2 ** $power);

        return min(86400, $seconds);
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return array{id: string, key: string, type: string, config: array<string, mixed>}
     */
    private function buildTargetFromRow(array $row): array
    {
        $config = [];

        if (!empty($row['target_config'])) {
            $decodedConfig = json_decode((string) $row['target_config'], true);
            $config        = $decodedConfig === (array) $decodedConfig ? $decodedConfig : [];
        }

        $destinationId = (string) ($row['destination_id'] ?? '');
        $targetKey     = (string) ($row['target_key'] ?? '');
        $targetType    = (string) ($row['target_type'] ?? '');

        return [
            'id'     => $destinationId === '' ? $targetKey : $destinationId,
            'key'    => $targetKey,
            'type'   => $targetType,
            'config' => $config,
        ];
    }

    /**
     * @param array{id: string, key: string, type: string, config: array<string, mixed>} $target
     */
    private function dispatchDeadDeliveryEvent(
        DomainEvent $event,
        array $target,
        string $deliveryId,
        int $attempt,
        string $errorMessage,
    ): void {
        $resultEvent = new OutboxDeliveryResultEvent(
            Context::createDefaultContext(),
            $event,
            $deliveryId,
            (string) ($target['id'] ?? ''),
            (string) ($target['key'] ?? ''),
            (string) ($target['type'] ?? ''),
            ($target['config'] ?? null) === (array) ($target['config'] ?? null) ? $target['config'] : [],
            $attempt,
            'dead',
            $errorMessage
        );

        $this->eventDispatcher->dispatch($resultEvent, OutboxDeliveryResultEvent::EVENT_NAME_FAILED);
    }
}

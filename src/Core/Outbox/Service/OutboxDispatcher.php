<?php declare(strict_types=1);

namespace Fib\OutboxBridge\Core\Outbox\Service;

use Fib\OutboxBridge\Core\Outbox\Config\OutboxSettings;
use Fib\OutboxBridge\Core\Outbox\Domain\DomainEvent;
use Fib\OutboxBridge\Core\Outbox\Publisher\EventPublisherInterface;
use Fib\OutboxBridge\Core\Outbox\Repository\OutboxRepository;
use Psr\Log\LoggerInterface;

class OutboxDispatcher
{
    public function __construct(
        private readonly OutboxRepository $repository,
        private readonly EventPublisherInterface $publisher,
        private readonly OutboxSettings $settings,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @return array<string, int>
     */
    public function dispatchBatch(?int $limit = null, ?string $workerId = null): array
    {
        $batchLimit = $limit ?? $this->settings->getDispatchBatchSize();
        $worker = $workerId;

        if (empty($worker)) {
            $worker = sprintf('outbox-%s', bin2hex(random_bytes(6)));
        }

        $rows = $this->repository->claimBatch($batchLimit, $worker, $this->settings->getLockSeconds());

        $result = [
            'claimed' => count($rows),
            'published' => 0,
            'retried' => 0,
            'dead' => 0,
            'errors' => 0,
        ];

        foreach ($rows as $row) {
            $id = (string) ($row['id'] ?? '');
            if ($id === '') {
                continue;
            }

            try {
                $this->publisher->publish(DomainEvent::fromOutboxRow($row));
                $this->repository->markPublished($id);
                ++$result['published'];
            } catch (\Throwable $exception) {
                ++$result['errors'];
                $attempts = ((int) ($row['attempts'] ?? 0)) + 1;
                $errorMessage = $exception->getMessage();

                if ($attempts >= $this->settings->getMaxAttempts()) {
                    $this->repository->markDead($id, $attempts, $errorMessage);
                    ++$result['dead'];
                } else {
                    $this->repository->reschedule(
                        $id,
                        $attempts,
                        (new \DateTimeImmutable())->modify(sprintf('+%d seconds', $this->getBackoffSeconds($attempts))),
                        $errorMessage
                    );
                    ++$result['retried'];
                }

                $this->logger->error('Outbox publish failed.', [
                    'eventId' => $id,
                    'attempts' => $attempts,
                    'error' => $exception,
                ]);
            }
        }

        return $result;
    }

    private function getBackoffSeconds(int $attempts): int
    {
        $power = max(0, min(10, $attempts - 1));
        $seconds = 60 * (2 ** $power);

        return min(86400, $seconds);
    }
}

<?php declare(strict_types=1);

namespace Fib\OutboxBridge\Core\Flow\Action;

use Fib\OutboxBridge\Core\Flow\Service\FlowOutboxEnqueueService;
use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Flow\Dispatching\Action\FlowAction;
use Shopware\Core\Content\Flow\Dispatching\StorableFlow;

class EnqueueDestinationFlowAction extends FlowAction
{
    public function __construct(
        private readonly FlowOutboxEnqueueService $enqueueService,
        private readonly LoggerInterface $logger
    ) {
    }

    public static function getName(): string
    {
        return 'action.fib.outbox.send.to.destination';
    }

    /**
     * @return array<int, string>
     */
    public function requirements(): array
    {
        return [];
    }

    public function handleFlow(StorableFlow $flow): void
    {
        $wasEnqueued = $this->enqueueService->enqueueForConfiguredDestination($flow, self::getName());
        if ($wasEnqueued) {
            return;
        }

        $config = $flow->getConfig();

        $this->logger->warning('Flow action could not enqueue outbox delivery because destination configuration is invalid.', [
            'actionName' => self::getName(),
            'flowName' => $flow->getName(),
            'destinationType' => $config['destinationType'] ?? null,
            'destinationId' => $config['destinationId'] ?? null,
        ]);
    }
}

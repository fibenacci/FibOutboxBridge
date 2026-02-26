<?php declare(strict_types=1);

namespace Fib\OutboxBridge\ScheduledTask;

use Fib\OutboxBridge\Core\Outbox\Config\OutboxSettings;
use Fib\OutboxBridge\Core\Outbox\Service\OutboxDispatcher;
use Psr\Log\LoggerInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\MessageQueue\ScheduledTask\ScheduledTaskHandler;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(handles: DispatchOutboxTask::class)]
class DispatchOutboxTaskHandler extends ScheduledTaskHandler
{
    public function __construct(
        EntityRepository $scheduledTaskRepository,
        LoggerInterface $exceptionLogger,
        private readonly OutboxDispatcher $dispatcher,
        private readonly OutboxSettings $settings
    ) {
        parent::__construct($scheduledTaskRepository, $exceptionLogger);
    }

    public function run(): void
    {
        $this->dispatcher->dispatchBatch($this->settings->getDispatchBatchSize(), 'scheduled-task');
    }
}

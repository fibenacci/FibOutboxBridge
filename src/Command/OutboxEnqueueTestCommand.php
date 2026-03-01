<?php

declare(strict_types=1);

namespace Fib\OutboxBridge\Command;

use Fib\OutboxBridge\Core\Outbox\Domain\DomainEvent;
use Fib\OutboxBridge\Core\Outbox\Repository\OutboxRepository;
use Shopware\Core\Framework\Uuid\Uuid;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'fib:outbox:enqueue-test',
    description: 'Enqueues a test event to validate the outbox dispatcher/publisher path.'
)]
class OutboxEnqueueTestCommand extends Command
{
    public function __construct(
        private readonly OutboxRepository $repository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument(
            'eventName',
            InputArgument::OPTIONAL,
            'Event name',
            'system.outbox.ping.v1'
        );

        $this->addArgument(
            'aggregateType',
            InputArgument::OPTIONAL,
            'Aggregate type',
            'system'
        );

        $this->addArgument(
            'aggregateId',
            InputArgument::OPTIONAL,
            'Aggregate id',
            Uuid::randomHex()
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $eventName     = (string) $input->getArgument('eventName');
        $aggregateType = (string) $input->getArgument('aggregateType');
        $aggregateId   = (string) $input->getArgument('aggregateId');

        $event = DomainEvent::create($eventName, $aggregateType, $aggregateId, [
            'message'   => 'FibOutboxBridge test event',
            'emittedAt' => (new \DateTimeImmutable())->format(\DATE_ATOM),
        ], [
            'source' => 'cli',
        ]);

        $this->repository->append($event);

        $output->writeln(sprintf('enqueued eventId=%s eventName=%s', $event->getId(), $event->getEventName()));

        return self::SUCCESS;
    }
}

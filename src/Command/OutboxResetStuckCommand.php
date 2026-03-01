<?php

declare(strict_types=1);

namespace Fib\OutboxBridge\Command;

use Fib\OutboxBridge\Core\Outbox\Repository\OutboxRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'fib:outbox:reset-stuck',
    description: 'Resets expired processing locks back to pending.'
)]
class OutboxResetStuckCommand extends Command
{
    public function __construct(
        private readonly OutboxRepository $repository,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $updated = $this->repository->resetExpiredProcessingLocks();
        $output->writeln(sprintf('reset=%d', $updated));

        return self::SUCCESS;
    }
}

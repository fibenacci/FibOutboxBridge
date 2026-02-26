<?php declare(strict_types=1);

namespace Fib\OutboxBridge\Command;

use Fib\OutboxBridge\Core\Outbox\Repository\OutboxRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'fib:outbox:stats',
    description: 'Shows outbox queue counts and oldest pending lag.'
)]
class OutboxStatsCommand extends Command
{
    public function __construct(
        private readonly OutboxRepository $repository
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $counts = $this->repository->getStatusCounts();
        $lag = $this->repository->getOldestPendingLagSeconds();

        $output->writeln(sprintf(
            'pending=%d processing=%d published=%d dead=%d lag_seconds=%s',
            $counts[OutboxRepository::STATUS_PENDING],
            $counts[OutboxRepository::STATUS_PROCESSING],
            $counts[OutboxRepository::STATUS_PUBLISHED],
            $counts[OutboxRepository::STATUS_DEAD],
            $lag ?: 'n/a'
        ));

        return self::SUCCESS;
    }
}

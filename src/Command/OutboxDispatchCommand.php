<?php declare(strict_types=1);

namespace Fib\OutboxBridge\Command;

use Fib\OutboxBridge\Core\Outbox\Service\OutboxDispatcher;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'fib:outbox:dispatch',
    description: 'Dispatches one outbox batch to the configured external publisher.'
)]
class OutboxDispatchCommand extends Command
{
    public function __construct(
        private readonly OutboxDispatcher $dispatcher
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'limit',
            null,
            InputOption::VALUE_REQUIRED,
            'Max events to claim in one batch',
            '100'
        );

        $this->addOption(
            'worker',
            null,
            InputOption::VALUE_REQUIRED,
            'Optional worker id',
            ''
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $limit = max(1, (int) $input->getOption('limit'));
        $worker = (string) $input->getOption('worker');

        $result = $this->dispatcher->dispatchBatch($limit, $worker ?: null);

        $output->writeln(sprintf(
            'claimed=%d published=%d retried=%d dead=%d errors=%d',
            $result['claimed'],
            $result['published'],
            $result['retried'],
            $result['dead'],
            $result['errors']
        ));

        return self::SUCCESS;
    }
}

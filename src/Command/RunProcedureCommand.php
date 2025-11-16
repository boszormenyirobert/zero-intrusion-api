<?php

namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:run-procedure')]
class RunProcedureCommand extends Command
{
    public function __construct(private Connection $conn)
    {
        parent::__construct();
    }

    // Optional for use with cron jobs.
    // The cleanup logic is currently handled by a MySQL EVENT, 
    // so this command is only needed if manual or external triggering is required.
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->conn->executeQuery('CALL delete_unvalid_process()');
        return Command::SUCCESS;
    }
}

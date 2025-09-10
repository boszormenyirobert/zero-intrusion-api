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

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $this->conn->executeQuery('CALL deleteUnvalidProcess()');
        return Command::SUCCESS;

        // crontab php8.2-cli /kunden/homepages/12/.../htdocs/easylogin/bin/console app:run-procedure
    }
}

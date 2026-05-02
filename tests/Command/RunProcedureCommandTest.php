<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Command\RunProcedureCommand;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class RunProcedureCommandTest extends TestCase
{
    public function testExecuteRunsStoredProcedureAndReturnsSuccess(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection
            ->expects(self::once())
            ->method('executeQuery')
            ->with('CALL delete_unvalid_process()');

        $tester = new CommandTester(new RunProcedureCommand($connection));

        self::assertSame(Command::SUCCESS, $tester->execute([]));
    }
}
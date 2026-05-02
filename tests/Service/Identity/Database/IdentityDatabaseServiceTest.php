<?php

declare(strict_types=1);

namespace App\Tests\Service\Identity\Database;

use App\Entity\Identity;
use App\Service\Identity\Database\IdentityDatabaseService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class IdentityDatabaseServiceTest extends TestCase
{
    public function testAddIdentityPersistsAndFlushes(): void
    {
        $identity = new Identity();

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist')->with($identity);
        $entityManager->expects(self::once())->method('flush');

        $service = new IdentityDatabaseService(
            $this->createMock(LoggerInterface::class),
            $entityManager,
        );

        $service->addIdentity($identity);
        self::assertTrue(true);
    }

    public function testUpdateIdentityPersistsAndFlushes(): void
    {
        $identity = new Identity();

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist')->with($identity);
        $entityManager->expects(self::once())->method('flush');

        $service = new IdentityDatabaseService(
            $this->createMock(LoggerInterface::class),
            $entityManager,
        );

        $service->updateIdentity($identity);
        self::assertTrue(true);
    }
}

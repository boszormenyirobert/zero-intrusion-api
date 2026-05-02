<?php

declare(strict_types=1);

namespace App\Tests\Service\AccessRegistry\Database;

use App\Entity\AuthBridge;
use App\Repository\AuthBridgeRepository;
use App\Service\AccessRegistry\Database\LoginDatabaseService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class LoginDatabaseServiceTest extends TestCase
{
    public function testAddUserLoginPersistsAndFlushesEntity(): void
    {
        $authBridge = new AuthBridge();

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist')->with($authBridge);
        $entityManager->expects(self::once())->method('flush');

        $service = $this->createService(entityManager: $entityManager);

        self::assertSame($authBridge, $service->addUserLogin($authBridge));
    }

    public function testAddUserLoginWrapsPersistenceErrors(): void
    {
        $authBridge = new AuthBridge();

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->expects(self::once())
            ->method('persist')
            ->with($authBridge)
            ->willThrowException(new \Exception('db failure'));
        $entityManager->expects(self::never())->method('flush');

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects(self::once())
            ->method('critical')
            ->with('extensionValidCommunication: db failure');

        $service = $this->createService(entityManager: $entityManager, logger: $logger);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to start transaction: db failure');

        $service->addUserLogin($authBridge);
    }

    public function testRemoveUserLoginRemovesSingleEntityAndFlushes(): void
    {
        $authBridge = new AuthBridge();

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('remove')->with($authBridge);
        $entityManager->expects(self::once())->method('flush');

        $service = $this->createService(entityManager: $entityManager);
        $service->removeUserLogin($authBridge);

        self::assertTrue(true);
    }

    public function testRemoveUserLoginsRemovesAllEntitiesAndFlushesOnce(): void
    {
        $first = new AuthBridge();
        $second = new AuthBridge();

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->expects(self::exactly(2))
            ->method('remove')
            ->with(self::callback(static function (AuthBridge $entity) use ($first, $second): bool {
                static $seen = [];
                $seen[] = $entity;

                return \in_array($entity, [$first, $second], true);
            }));
        $entityManager->expects(self::once())->method('flush');

        $service = $this->createService(entityManager: $entityManager);
        $service->removeUserLogins([$first, $second]);

        self::assertTrue(true);
    }

    private function createService(
        ?EntityManagerInterface $entityManager = null,
        ?LoggerInterface $logger = null,
    ): LoginDatabaseService {
        return new LoginDatabaseService(
            $entityManager ?? $this->createMock(EntityManagerInterface::class),
            $logger ?? $this->createMock(LoggerInterface::class),
            $this->createMock(AuthBridgeRepository::class),
        );
    }
}

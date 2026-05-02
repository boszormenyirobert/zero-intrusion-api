<?php

declare(strict_types=1);

namespace App\Tests\Service\AccessRegistry\CredentialHubHandler;

use App\Entity\AuthBridge;
use App\Repository\AuthBridgeRepository;
use App\Service\AccessRegistry\CredentialHubHandler\RegistryState;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class RegistryStateTest extends TestCase
{
    public function testSetRegistrationStateAddsStateToPayload(): void
    {
        $service = $this->createService();

        self::assertSame(
            ['publicId' => 'public-1', 'registrationState' => true],
            $service->setRegistrationState(['publicId' => 'public-1'], true)
        );
    }

    public function testRegistrationStateReturnsFalseWhenProcessMissing(): void
    {
        $repository = $this->createMock(AuthBridgeRepository::class);
        $repository
            ->expects(self::once())
            ->method('findOneBy')
            ->with(['registrationProcessId' => 'process-123'])
            ->willReturn(null);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('remove');
        $entityManager->expects(self::never())->method('flush');

        $service = $this->createService(repository: $repository, entityManager: $entityManager);
        $result = $service->registrationState('process-123', 'registrationProcessId');

        self::assertFalse($result->isProcess());
        self::assertTrue($result->getValidation());
        self::assertFalse($result->isProcessCheck());
    }

    public function testRegistrationStateReturnsPendingWhenProcessExistsButNotValidated(): void
    {
        $process = (new AuthBridge())->setProcessState(false);

        $repository = $this->createMock(AuthBridgeRepository::class);
        $repository
            ->expects(self::once())
            ->method('findOneBy')
            ->with(['registrationProcessId' => 'process-123'])
            ->willReturn($process);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('remove');
        $entityManager->expects(self::never())->method('flush');

        $service = $this->createService(repository: $repository, entityManager: $entityManager);
        $result = $service->registrationState('process-123', 'registrationProcessId');

        self::assertTrue($result->isProcess());
        self::assertTrue($result->getValidation());
        self::assertFalse($result->isProcessCheck());
    }

    public function testRegistrationStateRemovesValidatedProcess(): void
    {
        $process = (new AuthBridge())->setProcessState(true);

        $repository = $this->createMock(AuthBridgeRepository::class);
        $repository
            ->expects(self::once())
            ->method('findOneBy')
            ->with(['registrationProcessId' => 'process-123'])
            ->willReturn($process);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('remove')->with($process);
        $entityManager->expects(self::once())->method('flush');

        $service = $this->createService(repository: $repository, entityManager: $entityManager);
        $result = $service->registrationState('process-123', 'registrationProcessId');

        self::assertTrue($result->isProcess());
        self::assertTrue($result->getValidation());
        self::assertTrue($result->isProcessCheck());
    }

    private function createService(
        ?AuthBridgeRepository $repository = null,
        ?EntityManagerInterface $entityManager = null,
    ): RegistryState {
        return new RegistryState(
            $repository ?? $this->createMock(AuthBridgeRepository::class),
            $entityManager ?? $this->createMock(EntityManagerInterface::class),
            $this->createMock(LoggerInterface::class),
        );
    }
}

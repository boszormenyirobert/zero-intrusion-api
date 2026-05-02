<?php

declare(strict_types=1);

namespace App\Tests\Service\AccessRegistry\CredentialHubResolver;

use App\Entity\AccessRegistry;
use App\Repository\AccessRegistryRepository;
use App\Service\AccessRegistry\CredentialHubResolver\DeleteService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class DeleteServiceTest extends TestCase
{
    public function testDeleteAccessRegistryReturnsFalseWhenTargetMissing(): void
    {
        $repository = $this->createMock(AccessRegistryRepository::class);
        $repository
            ->expects(self::once())
            ->method('findOneBy')
            ->with(['targetId' => 'target-1'])
            ->willReturn(null);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('remove');
        $entityManager->expects(self::never())->method('flush');

        $service = $this->createService(repository: $repository, entityManager: $entityManager);

        self::assertFalse($service->deleteAccessRegistry('target-1'));
    }

    public function testDeleteAccessRegistryRemovesEntityWhenTargetExists(): void
    {
        $registry = new AccessRegistry();

        $repository = $this->createMock(AccessRegistryRepository::class);
        $repository
            ->expects(self::once())
            ->method('findOneBy')
            ->with(['targetId' => 'target-1'])
            ->willReturn($registry);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('remove')->with($registry);
        $entityManager->expects(self::once())->method('flush');

        $service = $this->createService(repository: $repository, entityManager: $entityManager);

        self::assertTrue($service->deleteAccessRegistry('target-1'));
    }

    public function testDeleteUserDomainCombinationReturnsNewCombinationWhenNoMatchFound(): void
    {
        $encrypted = (new AccessRegistry())
            ->setPublicId('public-2')
            ->setDomain('other.com')
            ->setTargetId('target-2');
        $decrypted = (new AccessRegistry())
            ->setDomain('other.com')
            ->setTargetId('target-2');

        $repository = $this->createMock(AccessRegistryRepository::class);
        $repository->expects(self::never())->method('findOneBy');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('remove');
        $entityManager->expects(self::never())->method('flush');

        $service = $this->createService(repository: $repository, entityManager: $entityManager);

        self::assertSame(
            ['newCombination' => true, 'existingPage' => []],
            $service->deleteUserDomainCombination(
                ['publicId' => 'public-1', 'domain' => 'example.com', 'targetId' => 'target-1'],
                [['encrypted' => $encrypted, 'decrypted' => $decrypted]]
            )
        );
    }

    public function testDeleteUserDomainCombinationDeletesMatchingEntry(): void
    {
        $encrypted = (new AccessRegistry())
            ->setPublicId('public-1')
            ->setDomain('example.com')
            ->setTargetId('target-1');
        $decrypted = (new AccessRegistry())
            ->setDomain('example.com')
            ->setTargetId('target-1');
        $stored = new AccessRegistry();

        $repository = $this->createMock(AccessRegistryRepository::class);
        $repository
            ->expects(self::once())
            ->method('findOneBy')
            ->with([
                'domain' => 'example.com',
                'publicId' => 'public-1',
                'targetId' => 'target-1',
            ])
            ->willReturn($stored);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('remove')->with($stored);
        $entityManager->expects(self::once())->method('flush');

        $service = $this->createService(repository: $repository, entityManager: $entityManager);

        self::assertSame(
            ['newCombination' => false, 'existingPage' => []],
            $service->deleteUserDomainCombination(
                ['publicId' => 'public-1', 'domain' => 'example.com', 'targetId' => 'target-1'],
                [['encrypted' => $encrypted, 'decrypted' => $decrypted]]
            )
        );
    }

    private function createService(
        ?AccessRegistryRepository $repository = null,
        ?EntityManagerInterface $entityManager = null,
    ): DeleteService {
        return new DeleteService(
            $repository ?? $this->createMock(AccessRegistryRepository::class),
            $entityManager ?? $this->createMock(EntityManagerInterface::class),
            $this->createMock(LoggerInterface::class),
        );
    }
}

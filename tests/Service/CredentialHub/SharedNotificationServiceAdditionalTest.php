<?php

declare(strict_types=1);

namespace App\Tests\Service\CredentialHub;

use App\Entity\Identity;
use App\Repository\AccessRegistryRepository;
use App\Repository\IdentityRepository;
use App\Service\CredentialHub\SharedNotificationService;
use App\Service\Firebase\FirebaseService;
use App\Service\Identity\Database\CrypterDatabaseIdentityService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class SharedNotificationServiceAdditionalTest extends TestCase
{
    public function testSendFcmNotificationIgnoresUnsupportedSourcesAndMissingPublicId(): void
    {
        $firebaseService = $this->createMock(FirebaseService::class);
        $firebaseService->expects(self::never())->method('manageFcm');

        $service = new SharedNotificationService(
            $firebaseService,
            $this->createMock(IdentityRepository::class),
            $this->createMock(AccessRegistryRepository::class),
            $this->createMock(CrypterDatabaseIdentityService::class),
            $this->createMock(LoggerInterface::class),
        );

        $service->sendFcmNotification('unknown', 'public-1', ['qr' => 'payload']);
        $service->sendFcmNotification('vaultRead', null, ['qr' => 'payload']);
        self::assertTrue(true);
    }

    public function testGetUserEmailByTargetIdReturnsNullPairForMissingDataAndLoggedExceptions(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error')->with(self::stringContains('Error retrieving user email: boom'));

        $accessRegistryRepository = $this->createMock(AccessRegistryRepository::class);
        $accessRegistryRepository
            ->expects(self::once())
            ->method('findOneBy')
            ->with(['targetId' => 'target-1'])
            ->willReturn(new class {
                public function getPublicId(): string
                {
                    return 'public-1';
                }
            });

        $identityRepository = $this->createMock(IdentityRepository::class);
        $identityRepository
            ->expects(self::once())
            ->method('findOneBy')
            ->with(['publicId' => 'public-1'])
            ->willReturn(new Identity());

        $crypter = $this->createMock(CrypterDatabaseIdentityService::class);
        $crypter->expects(self::once())->method('decryptFromDatabaseDevice')->willThrowException(new \RuntimeException('boom'));

        $service = new SharedNotificationService(
            $this->createMock(FirebaseService::class),
            $identityRepository,
            $accessRegistryRepository,
            $crypter,
            $logger,
        );

        self::assertSame(['email' => null, 'publicId' => null], $service->getUserEmailByTargetId([]));
        self::assertSame(['email' => null, 'publicId' => null], $service->getUserEmailByTargetId(['response' => [['targetId' => 'target-1']]]));
    }

    public function testGetUserEmailByTargetIdReturnsNullPairWhenTargetOrIdentityIsMissing(): void
    {
        $accessRegistryRepository = $this->createMock(AccessRegistryRepository::class);
        $accessRegistryRepository->expects(self::once())->method('findOneBy')->with(['targetId' => 'target-1'])->willReturn(null);

        $identityRepository = $this->createMock(IdentityRepository::class);
        $identityRepository->expects(self::never())->method('findOneBy');

        $service = new SharedNotificationService(
            $this->createMock(FirebaseService::class),
            $identityRepository,
            $accessRegistryRepository,
            $this->createMock(CrypterDatabaseIdentityService::class),
            $this->createMock(LoggerInterface::class),
        );

        self::assertSame(['email' => null, 'publicId' => null], $service->getUserEmailByTargetId(['response' => [['targetId' => 'target-1']]]));
    }
}
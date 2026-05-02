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

final class SharedNotificationServiceTest extends TestCase
{
    public function testSendFcmNotificationUsesMappedDescriptor(): void
    {
        $firebaseService = $this->createMock(FirebaseService::class);
        $firebaseService
            ->expects(self::once())
            ->method('manageFcm')
            ->with('user-public-id', 'From vault read', 'Forwarded the QR content, ordered by the user publicId', ['qr' => 'payload']);

        $service = new SharedNotificationService(
            $firebaseService,
            $this->createMock(IdentityRepository::class),
            $this->createMock(AccessRegistryRepository::class),
            $this->createMock(CrypterDatabaseIdentityService::class),
            $this->createMock(LoggerInterface::class),
        );

        $service->sendFcmNotification('vaultRead', 'user-public-id', ['qr' => 'payload']);
    }

    public function testGetUserEmailByTargetIdReturnsDecryptedIdentityDetails(): void
    {
        $user = new class {
            public function getPublicId(): string
            {
                return 'public-1';
            }
        };

        $encryptedIdentity = new Identity();
        $decryptedIdentity = (new Identity())
            ->setPublicId('public-1')
            ->setEmail('user@example.test');

        $accessRegistryRepository = $this->createMock(AccessRegistryRepository::class);
        $accessRegistryRepository
            ->expects(self::once())
            ->method('findOneBy')
            ->with(['targetId' => 'target-1'])
            ->willReturn($user);

        $identityRepository = $this->createMock(IdentityRepository::class);
        $identityRepository
            ->expects(self::once())
            ->method('findOneBy')
            ->with(['publicId' => 'public-1'])
            ->willReturn($encryptedIdentity);

        $crypterDatabaseIdentityService = $this->createMock(CrypterDatabaseIdentityService::class);
        $crypterDatabaseIdentityService
            ->expects(self::once())
            ->method('decryptFromDatabaseDevice')
            ->with($encryptedIdentity)
            ->willReturn($decryptedIdentity);

        $service = new SharedNotificationService(
            $this->createMock(FirebaseService::class),
            $identityRepository,
            $accessRegistryRepository,
            $crypterDatabaseIdentityService,
            $this->createMock(LoggerInterface::class),
        );

        self::assertSame(
            ['email' => 'user@example.test', 'publicId' => 'public-1'],
            $service->getUserEmailByTargetId(['response' => [['targetId' => 'target-1']]]),
        );
    }
}
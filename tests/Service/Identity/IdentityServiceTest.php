<?php

declare(strict_types=1);

namespace App\Tests\Service\Identity;

use App\Entity\Identity;
use App\Repository\AuthBridgeRepository;
use App\Repository\IdentityRepository;
use App\Service\Crypters\CrypterDatabaseLoginService;
use App\Service\Crypters\SodiumService;
use App\Service\Identity\Database\CrypterDatabaseIdentityService;
use App\Service\Identity\Database\IdentityDatabaseService;
use App\Service\Identity\IdentityService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class IdentityServiceTest extends TestCase
{
    public function testGetKeyEncryptsAndPersistsGeneratedIdentity(): void
    {
        $identityDatabaseService = $this->createMock(IdentityDatabaseService::class);
        $encryptedIdentity = new Identity();
        $identityDatabaseService->expects(self::once())->method('addIdentity')->with($encryptedIdentity);

        $crypterDatabaseIdentityService = $this->createMock(CrypterDatabaseIdentityService::class);
        $crypterDatabaseIdentityService
            ->expects(self::once())
            ->method('encryptDataObjectOrFail')
            ->with(self::callback(static function (array $payload): bool {
                return isset($payload['publicId'], $payload['privateId'], $payload['secret'], $payload['credentialSecret'], $payload['nfcEncryptionKey'])
                    && $payload['privateId'] === 'encrypted-private-id';
            }))
            ->willReturn($encryptedIdentity);

        $repository = $this->createMock(IdentityRepository::class);
        $repository->method('count')->willReturn(0);

        $sodium = $this->createMock(SodiumService::class);
        $sodium
            ->expects(self::once())
            ->method('sodiumEncrypt')
            ->with(self::isType('string'), self::isType('string'))
            ->willReturn('encrypted-private-id');

        $service = new IdentityService(
            $identityDatabaseService,
            $crypterDatabaseIdentityService,
            $repository,
            $this->createMock(CrypterDatabaseLoginService::class),
            $this->createMock(LoggerInterface::class),
            $this->createMock(AuthBridgeRepository::class),
            $sodium,
        );

        $dto = $service->getKey();

        self::assertNotSame('encrypted-private-id', $dto->getPrivateId());
        self::assertNotSame('', $dto->getPrivateId());
        self::assertNotSame('', $dto->getNfcEncryptionKey());
    }

    public function testUpdateIdentityRecoverySettingsPersistsEncryptedContactChangesAndFcmToken(): void
    {
        $storedIdentity = (new Identity())
            ->setPublicId('public-1')
            ->setFcmToken(['existing-token']);
        $decryptedIdentity = (new Identity())
            ->setPublicId('public-1')
            ->setPrivateId('db-private-id')
            ->setSecret('shared-secret')
            ->setIv(base64_encode(random_bytes(16)));
        $encryptedUpdatedIdentity = (new Identity())
            ->setEmail('encrypted-email')
            ->setPhone('encrypted-phone')
            ->setPrivacyPolicy(true);

        $repository = $this->createMock(IdentityRepository::class);
        $repository
            ->expects(self::exactly(2))
            ->method('findOneBy')
            ->with(['publicId' => 'public-1'])
            ->willReturnOnConsecutiveCalls($storedIdentity, $storedIdentity);

        $crypterDatabaseLoginService = $this->createMock(CrypterDatabaseLoginService::class);
        $crypterDatabaseLoginService
            ->expects(self::once())
            ->method('decryptFromDatabaseIdentity')
            ->with($storedIdentity)
            ->willReturn($decryptedIdentity);

        $crypterDatabaseIdentityService = $this->createMock(CrypterDatabaseIdentityService::class);
        $crypterDatabaseIdentityService
            ->expects(self::once())
            ->method('encyptUpdateIdentity')
            ->with($decryptedIdentity, self::callback(static fn (array $user): bool => $user['publicId'] === 'public-1'))
            ->willReturn($encryptedUpdatedIdentity);
        $crypterDatabaseIdentityService
            ->expects(self::once())
            ->method('encryptData')
            ->with('new-fcm-token', base64_decode($decryptedIdentity->getIv()))
            ->willReturn('encrypted-fcm-token');

        $sodium = $this->createMock(SodiumService::class);
        $sodium
            ->expects(self::exactly(2))
            ->method('sodiumDecrypt')
            ->withConsecutive(['db-private-id', 'shared-secret'], ['request-private-id', 'shared-secret'])
            ->willReturnOnConsecutiveCalls('plain-private-id', 'plain-private-id');

        $identityDatabaseService = $this->createMock(IdentityDatabaseService::class);
        $identityDatabaseService
            ->expects(self::once())
            ->method('updateIdentity')
            ->with(self::callback(static function (Identity $identity): bool {
                return $identity->getEmail() === 'encrypted-email'
                    && $identity->getPhone() === 'encrypted-phone'
                    && $identity->isPrivacyPolicy() === true
                    && $identity->getFcmToken() === ['existing-token', 'encrypted-fcm-token'];
            }));

        $service = new IdentityService(
            $identityDatabaseService,
            $crypterDatabaseIdentityService,
            $repository,
            $crypterDatabaseLoginService,
            $this->createMock(LoggerInterface::class),
            $this->createMock(AuthBridgeRepository::class),
            $sodium,
        );

        $service->updateIdentityRecoverySettings([
            'publicId' => 'public-1',
            'privateId' => 'request-private-id',
            'email' => 'user@example.test',
            'phone' => '+4912345678',
            'privacyPolicy' => true,
            'fcmToken' => 'new-fcm-token',
        ]);
    }

    public function testGetSecretReturnsMatchingDecryptedIdentity(): void
    {
        $first = new Identity();
        $second = new Identity();
        $firstDecrypted = (new Identity())->setPhone('111')->setEmail('first@example.test');
        $secondDecrypted = (new Identity())->setPhone('222')->setEmail('second@example.test');

        $repository = $this->createMock(IdentityRepository::class);
        $repository->expects(self::once())->method('findAll')->willReturn([$first, $second]);

        $crypterDatabaseIdentityService = $this->createMock(CrypterDatabaseIdentityService::class);
        $crypterDatabaseIdentityService
            ->expects(self::exactly(2))
            ->method('decryptFromDatabaseDevice')
            ->willReturnOnConsecutiveCalls($firstDecrypted, $secondDecrypted);

        $service = new IdentityService(
            $this->createMock(IdentityDatabaseService::class),
            $crypterDatabaseIdentityService,
            $repository,
            $this->createMock(CrypterDatabaseLoginService::class),
            $this->createMock(LoggerInterface::class),
            $this->createMock(AuthBridgeRepository::class),
            $this->createMock(SodiumService::class),
        );

        self::assertSame($secondDecrypted, $service->getSecret([
            'phone' => '222',
            'email' => 'second@example.test',
        ]));
    }
}
<?php

declare(strict_types=1);

namespace App\Tests\Service\Device\Nfc;

use App\Entity\Identity;
use App\Repository\IdentityRepository;
use App\Service\Crypters\CrypterDatabaseLoginService;
use App\Service\Crypters\SodiumService;
use App\Service\Device\Nfc\NfcUsersService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class NfcUsersServiceAdditionalTest extends TestCase
{
    public function testHandleSkipsUserWhenEncryptionFails(): void
    {
        $identity = (new Identity())
            ->setPublicId('public-1')
            ->setPrivateId('encrypted-private-id')
            ->setSecret('secret-1')
            ->setCredentialSecret('credential-1')
            ->setEmail('user@example.test')
            ->setPhone('+3612345678')
            ->setIv(base64_encode(random_bytes(16)))
            ->setNfcEncryptionKey('nfc-key');

        $identityRepository = $this->createMock(IdentityRepository::class);
        $identityRepository
            ->expects(self::once())
            ->method('findAll')
            ->willReturn([$identity]);

        $crypter = $this->createMock(CrypterDatabaseLoginService::class);
        $crypter
            ->expects(self::once())
            ->method('decryptFromDatabaseidentity')
            ->with($identity)
            ->willReturn($identity);

        $sodiumService = $this->createMock(SodiumService::class);
        $sodiumService
            ->expects(self::once())
            ->method('sodiumDecrypt')
            ->with('encrypted-private-id', 'secret-1')
            ->willReturn('private-1');
        $sodiumService
            ->expects(self::once())
            ->method('sodiumEncrypt')
            ->willThrowException(new \RuntimeException('boom'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects(self::once())
            ->method('critical')
            ->with('NFC USERS ENCRYPTION ERROR boom');

        $service = new NfcUsersService($identityRepository, $crypter, $sodiumService, $logger);

        self::assertSame(['users' => []], $service->handle());
    }
}

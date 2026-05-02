<?php

declare(strict_types=1);

namespace App\Tests\Service\Identity\Database;

use App\Entity\Identity;
use App\Service\Identity\Database\CrypterDatabaseIdentityService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;

final class CrypterDatabaseIdentityServiceTest extends TestCase
{
    public function testEncryptDataObjectOrFailCanBeDecryptedBackForDeviceFields(): void
    {
        $service = new CrypterDatabaseIdentityService($this->createParameterBag());

        $encrypted = $service->encryptDataObjectOrFail([
            'publicId' => 'public-1',
            'privateId' => 'private-1',
            'secret' => 'secret-1',
            'email' => 'user@example.test',
            'phone' => '+3612345678',
        ]);

        $decrypted = $service->decryptFromDatabaseDevice($encrypted);

        self::assertSame('public-1', $decrypted->getPublicId());
        self::assertSame('private-1', $decrypted->getPrivateId());
        self::assertSame('secret-1', $decrypted->getSecret());
        self::assertSame('user@example.test', $decrypted->getEmail());
        self::assertSame('+3612345678', $decrypted->getPhone());
    }

    public function testEncyptDataObjectPreservesLegacyAdapter(): void
    {
        $service = new CrypterDatabaseIdentityService($this->createParameterBag());

        $encrypted = $service->encyptDataObject([
            'publicId' => 'public-1',
            'privateId' => 'private-1',
            'secret' => 'secret-1',
            'email' => 'user@example.test',
            'phone' => '+3612345678',
        ]);

        self::assertSame('public-1', $encrypted->getPublicId());
        self::assertNotSame('private-1', $encrypted->getPrivateId());
        self::assertNotSame('secret-1', $encrypted->getSecret());
    }

    public function testDecryptFromDatabaseDeviceRejectsInvalidIvLength(): void
    {
        $service = new CrypterDatabaseIdentityService($this->createParameterBag());
        $identity = (new Identity())
            ->setIv(base64_encode('short'))
            ->setPublicId('public-1');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid IV length, expected 16 bytes');

        $service->decryptFromDatabaseDevice($identity);
    }

    public function testDecryptDataRejectsInvalidBase64Payload(): void
    {
        $service = new CrypterDatabaseIdentityService($this->createParameterBag());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Decryption failed: invalid base64 payload');

        $service->decryptData('%%%invalid-base64%%%', random_bytes(16));
    }

    private function createParameterBag(): ContainerBagInterface&MockObject
    {
        $params = $this->createMock(ContainerBagInterface::class);
        $params
            ->method('get')
            ->willReturnMap([
                ['DATABASE_HASH_SECRET', '0123456789abcdef0123456789abcdef'],
            ]);

        return $params;
    }
}
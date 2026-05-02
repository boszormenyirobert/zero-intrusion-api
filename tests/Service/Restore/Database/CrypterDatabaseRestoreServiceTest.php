<?php

declare(strict_types=1);

namespace App\Tests\Service\Restore\Database;

use App\Entity\Restore;
use App\Service\Restore\Database\CrypterDatabaseRestoreService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;

final class CrypterDatabaseRestoreServiceTest extends TestCase
{
    public function testEncryptSourceDataOrFailCanBeDecryptedBack(): void
    {
        $service = new CrypterDatabaseRestoreService($this->createParameterBag());
        $restore = (new Restore())
            ->setPublicId('public-1')
            ->setPrivateId('private-1')
            ->setSecret('secret-1')
            ->setPin(1234)
            ->setHash('hash-1');

        $encrypted = $service->encryptSourceDataOrFail($restore);
        $decrypted = $service->decryptFromDatabase($encrypted);

        self::assertSame('public-1', $decrypted->getPublicId());
        self::assertSame('private-1', $decrypted->getPrivateId());
        self::assertSame('secret-1', $decrypted->getSecret());
    }

    public function testEncyptSourceDataPreservesLegacyAdapter(): void
    {
        $service = new CrypterDatabaseRestoreService($this->createParameterBag());
        $restore = (new Restore())
            ->setPublicId('public-1')
            ->setPrivateId('private-1')
            ->setSecret('secret-1')
            ->setPin(1234)
            ->setHash('hash-1');

        $encrypted = $service->encyptSourceData($restore);

        self::assertSame('public-1', $encrypted->getPublicId());
        self::assertNotSame('private-1', $encrypted->getPrivateId());
        self::assertNotSame('secret-1', $encrypted->getSecret());
    }

    public function testDecryptFromDatabaseRejectsInvalidIvLength(): void
    {
        $service = new CrypterDatabaseRestoreService($this->createParameterBag());
        $restore = (new Restore())
            ->setIv(base64_encode('short'))
            ->setPublicId('public-1');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid IV length, expected 16 bytes');

        $service->decryptFromDatabase($restore);
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
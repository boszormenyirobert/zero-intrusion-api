<?php

declare(strict_types=1);

namespace App\Tests\Service\Corporate;

use App\Service\Corporate\CorporateRegistrationDatabaseService;
use App\Service\Corporate\IdentityService;
use App\Service\Crypters\CrypterDatabaseService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;

final class IdentityServiceTest extends TestCase
{
    public function testGetIdentityThrowsWhenInitializeIdentityWasNotCalled(): void
    {
        $service = new IdentityService(
            $this->createMock(ContainerBagInterface::class),
            $this->createMock(CorporateRegistrationDatabaseService::class),
            $this->createMock(CrypterDatabaseService::class),
            $this->createMock(LoggerInterface::class),
        );

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Identity not initialized. Call initializeIdentity() first.');

        $service->getIdentity();
    }

    public function testGetIdentityRemovesPrivateKeyFromInitializedIdentity(): void
    {
        $service = new IdentityService(
            $this->createMock(ContainerBagInterface::class),
            $this->createMock(CorporateRegistrationDatabaseService::class),
            $this->createMock(CrypterDatabaseService::class),
            $this->createMock(LoggerInterface::class),
        );

        $property = new \ReflectionProperty($service, 'newIdentity');
        $property->setValue($service, [
            'corporate_id' => 'corp-1',
            'corporate_id_key' => 'key-1',
            'corporate_id_secret' => 'secret-1',
            'ssl_public_key' => 'public-key',
            'ssl_private_key' => 'private-key',
        ]);

        $identity = $service->getIdentity();

        self::assertSame('corp-1', $identity['corporate_id']);
        self::assertSame('key-1', $identity['corporate_id_key']);
        self::assertSame('secret-1', $identity['corporate_id_secret']);
        self::assertSame('public-key', $identity['ssl_public_key']);
        self::assertArrayNotHasKey('ssl_private_key', $identity);
    }

    public function testResolveOpenSslConfigPathReturnsExistingPathOrNull(): void
    {
        $service = new IdentityService(
            $this->createMock(ContainerBagInterface::class),
            $this->createMock(CorporateRegistrationDatabaseService::class),
            $this->createMock(CrypterDatabaseService::class),
            $this->createMock(LoggerInterface::class),
        );

        $method = new \ReflectionMethod($service, 'resolveOpenSslConfigPath');
        $tempFile = tempnam(sys_get_temp_dir(), 'openssl');
        self::assertIsString($tempFile);

        try {
            self::assertSame($tempFile, $method->invoke($service, $tempFile));
            self::assertNull($method->invoke($service, 'C:/missing/openssl.cnf'));
        } finally {
            @unlink($tempFile);
        }
    }
}
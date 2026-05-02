<?php

declare(strict_types=1);

namespace App\Tests\Service\AccessRegistry\Database;

use App\Entity\AccessRegistry;
use App\Service\AccessRegistry\Database\CrypterDatabaseAccessRegistryService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;

final class CrypterDatabaseAccessRegistryServiceTest extends TestCase
{
    public function testEncryptDataObjectOrFailEncryptsDomainPayloadThatCanBeDecryptedBack(): void
    {
        $service = $this->createService();

        $encrypted = $service->encryptDataObjectOrFail([
            'registrationState' => true,
            'publicId' => 'public-1',
            'registrationProcessId' => 'process-1',
            'targetId' => 'target-1',
            'corporateId' => 'corp-1',
            'domain' => 'example.com',
            'userCredential' => 'secret-credential',
            'description' => 'Primary account',
        ], 'domain');

        $decrypted = $service->decryptFromDatabase($encrypted, 'domain');

        self::assertInstanceOf(AccessRegistry::class, $decrypted);
        self::assertSame('public-1', $decrypted->getPublicId());
        self::assertSame('corp-1', $decrypted->getCorporateId());
        self::assertSame('target-1', $decrypted->getTargetId());
        self::assertSame('example.com', $decrypted->getDomain());
        self::assertSame('secret-credential', $decrypted->getUserCredential());
        self::assertSame('Primary account', $decrypted->getDescription());
    }

    public function testEncryptDataObjectOrFailEncryptsApplicationPayloadThatCanBeDecryptedBack(): void
    {
        $service = $this->createService();

        $encrypted = $service->encryptDataObjectOrFail([
            'registrationState' => true,
            'publicId' => 'public-1',
            'registrationProcessId' => 'process-1',
            'targetId' => 'target-1',
            'corporateId' => 'corp-1',
            'application' => 'mail',
            'userCredential' => 'secret-credential',
            'description' => 'Primary app',
        ], 'application');

        $decrypted = $service->decryptFromDatabase($encrypted, 'application');

        self::assertInstanceOf(AccessRegistry::class, $decrypted);
        self::assertSame('public-1', $decrypted->getPublicId());
        self::assertSame('corp-1', $decrypted->getCorporateId());
        self::assertSame('target-1', $decrypted->getTargetId());
        self::assertSame('mail', $decrypted->getApplication());
        self::assertSame('secret-credential', $decrypted->getUserCredential());
        self::assertSame('Primary app', $decrypted->getDescription());
    }

    public function testEncyptDataObjectPreservesLegacyAdapter(): void
    {
        $service = $this->createService();

        $encrypted = $service->encyptDataObject([
            'registrationState' => true,
            'publicId' => 'public-1',
            'registrationProcessId' => 'process-1',
            'targetId' => 'target-1',
            'domain' => 'example.com',
            'userCredential' => 'secret-credential',
        ], 'domain');

        self::assertSame('public-1', $encrypted->getPublicId());
        self::assertNotSame('example.com', $encrypted->getDomain());
        self::assertNotSame('secret-credential', $encrypted->getUserCredential());
    }

    public function testDecryptDomainWithoutDescriptionLeavesDescriptionEmpty(): void
    {
        $service = $this->createService();

        $encrypted = $service->encyptDataObject([
            'registrationState' => true,
            'publicId' => 'public-1',
            'registrationProcessId' => 'process-1',
            'targetId' => 'target-1',
            'domain' => 'example.com',
            'userCredential' => 'secret-credential',
        ], 'domain');

        $decrypted = $service->decryptFromDatabase($encrypted, 'domain', false);

        self::assertInstanceOf(AccessRegistry::class, $decrypted);
        self::assertSame('example.com', $decrypted->getDomain());
        self::assertSame('secret-credential', $decrypted->getUserCredential());
        self::assertNull($decrypted->getDescription());
    }

    public function testDecryptFromDatabaseRejectsInvalidIvLength(): void
    {
        $service = $this->createService();
        $registry = (new AccessRegistry())
            ->setIv(base64_encode('short'))
            ->setUserCredential('credential')
            ->setDomain('domain');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid IV length, expected 16 bytes');

        $service->decryptFromDatabase($registry, 'domain');
    }

    public function testDecryptFromDatabaseOrFailRejectsUnknownType(): void
    {
        $service = $this->createService();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('invalid type');

        $service->decryptFromDatabaseOrFail(new AccessRegistry(), 'unknown');
    }

    public function testDecryptFromDatabaseReturnsErrorArrayForUnknownType(): void
    {
        $service = $this->createService();

        self::assertSame(['error' => 'invalid type'], $service->decryptFromDatabase(new AccessRegistry(), 'unknown'));
    }

    private function createService(): CrypterDatabaseAccessRegistryService
    {
        return new CrypterDatabaseAccessRegistryService(
            $this->createParameterBag(),
            $this->createMock(LoggerInterface::class),
        );
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

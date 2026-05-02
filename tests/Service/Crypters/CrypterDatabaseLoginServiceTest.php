<?php

declare(strict_types=1);

namespace App\Tests\Service\Crypters;

use App\Entity\AuthBridge;
use App\Entity\Identity;
use App\Service\Crypters\CrypterDatabaseLoginService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;

final class CrypterDatabaseLoginServiceTest extends TestCase
{
    public function testEncryptDataFromArrayProducesDecryptableDomainCredential(): void
    {
        $service = $this->createService();

        $encrypted = $service->encryptDataFromArray([
            'domainProcessId' => 'process-123',
            'userCredential' => (object) [
                'userName' => 'john.doe',
                'userPassword' => 'super-secret',
            ],
        ]);

        $decrypted = $service->decryptFromDatabaseOrFail($encrypted, 'domain');

        self::assertSame('{"userName":"john.doe","userPassword":"super-secret"}', $decrypted->getUserCredential());
    }

    public function testDecryptFromDatabaseReturnsFalseForUnsupportedType(): void
    {
        $service = $this->createService();

        self::assertFalse($service->decryptFromDatabase(new AuthBridge(), 'unknown'));
    }

    public function testDecryptFromDatabaseOrFailThrowsForUnsupportedType(): void
    {
        $service = $this->createService();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported decrypt type: unknown');

        $service->decryptFromDatabaseOrFail(new AuthBridge(), 'unknown');
    }

    public function testDecryptFromDatabaseReturnsFalseWhenApplicationsMissing(): void
    {
        $service = $this->createService();
        $bridge = (new AuthBridge())->setIv(base64_encode(random_bytes(16)));

        self::assertFalse($service->decryptFromDatabase($bridge, 'applications'));
    }

    public function testDecryptFromDatabaseOrFailThrowsWhenApplicationsMissing(): void
    {
        $service = $this->createService();
        $bridge = (new AuthBridge())->setIv(base64_encode(random_bytes(16)));

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('Missing applications payload.');

        $service->decryptFromDatabaseOrFail($bridge, 'applications');
    }

    public function testDecryptFromDatabaseIdentityReturnsDecryptedIdentity(): void
    {
        $service = $this->createService();
        $iv = random_bytes(16);
        $identity = (new Identity())
            ->setIv(base64_encode($iv))
            ->setPublicId('public-123')
            ->setPrivateId($this->encryptRaw('private-123', $iv))
            ->setSecret($this->encryptRaw('secret-123', $iv))
            ->setEmail($this->encryptRaw('john@example.test', $iv))
            ->setCredentialSecret($this->encryptRaw('credential-secret-123', $iv));

        $decrypted = $service->decryptFromDatabaseidentity($identity);

        self::assertSame('public-123', $decrypted->getPublicId());
        self::assertSame('private-123', $decrypted->getPrivateId());
        self::assertSame('secret-123', $decrypted->getSecret());
        self::assertSame('john@example.test', $decrypted->getEmail());
        self::assertSame('credential-secret-123', $decrypted->getCredentialSecret());
    }

    public function testDecryptFromDatabaseToHmacReturnsDecryptedSecret(): void
    {
        $service = $this->createService();
        $iv = random_bytes(16);
        $bridge = (new AuthBridge())
            ->setIv(base64_encode($iv))
            ->setSecret($this->encryptRaw('hmac-secret-123', $iv));

        $decrypted = $service->decryptFromDatabaseToHmac($bridge);

        self::assertSame('hmac-secret-123', $decrypted->getSecret());
    }

    public function testDecryptFromDatabaseIdentityThrowsRuntimeExceptionOnDecryptionFailure(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('critical');
        $service = $this->createService($logger);

        $identity = (new Identity())
            ->setIv(base64_encode(random_bytes(16)))
            ->setPublicId('public-123')
            ->setPrivateId('not-valid-base64')
            ->setSecret('not-valid-base64')
            ->setEmail('not-valid-base64')
            ->setCredentialSecret('not-valid-base64');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Decryption error in CrypterDatabaseLoginService');

        $service->decryptFromDatabaseidentity($identity);
    }

    private function createService(?LoggerInterface $logger = null): CrypterDatabaseLoginService
    {
        return new CrypterDatabaseLoginService(
            $this->createParameterBag(),
            $logger ?? $this->createMock(LoggerInterface::class),
        );
    }

    private function createParameterBag(): ContainerBagInterface&MockObject
    {
        $params = $this->createMock(ContainerBagInterface::class);
        $params
            ->method('get')
            ->willReturnMap([
                ['DATABASE_HASH_SECRET', '12345678901234567890123456789012'],
            ]);

        return $params;
    }

    private function encryptRaw(string $value, string $iv): string
    {
        $encrypted = openssl_encrypt($value, 'aes-256-cbc', '12345678901234567890123456789012', 0, $iv);
        self::assertNotFalse($encrypted);

        return base64_encode($encrypted);
    }
}

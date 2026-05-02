<?php

declare(strict_types=1);

namespace App\Tests\Service\Crypters;

use App\Entity\CorporateIdentity;
use App\Service\Crypters\CrypterDatabaseService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;

final class CrypterDatabaseServiceTest extends TestCase
{
    public function testEncryptDataObjectCanBeDecryptedBackToOriginalValues(): void
    {
        $service = new CrypterDatabaseService($this->createParameterBag());

        $encrypted = $service->encyptDataObject([
            'corporate_id' => 'corp-123',
            'corporate_id_key' => 'key-123',
            'corporate_id_secret' => 'secret-123',
            'ssl_private_key' => 'private-key-123',
            'ssl_public_key' => 'public-key-123',
        ]);
        $encrypted->setDomain('example.test');
        $encrypted->setCallbackUserLogin('https://example.test/login');
        $encrypted->setCallbackUserRegistration('https://example.test/register');

        $decrypted = $service->decryptFromDatabase($encrypted);

        self::assertSame('corp-123', $decrypted->getCorporateId());
        self::assertSame('key-123', $decrypted->getCorporateIdKey());
        self::assertSame('secret-123', $decrypted->getCorporateIdSecret());
        self::assertSame('private-key-123', $decrypted->getSslPrivateKey());
        self::assertSame('public-key-123', $decrypted->getSslPublicKey());
        self::assertSame('example.test', $decrypted->getDomain());
        self::assertSame('https://example.test/login', $decrypted->getCallbackUserLogin());
        self::assertSame('https://example.test/register', $decrypted->getCallbackUserRegistration());
    }

    public function testDecryptFromDatabaseRejectsInvalidIvLength(): void
    {
        $service = new CrypterDatabaseService($this->createParameterBag());
        $identity = (new CorporateIdentity())
            ->setIv(base64_encode('short'))
            ->setCorporateId('corp-123')
            ->setCorporateIdKey('key')
            ->setCorporateIdSecret('secret')
            ->setSslPrivateKey('private');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid IV length, expected 16 bytes');

        $service->decryptFromDatabase($identity);
    }

    public function testEncryptUserCredentialCanBeDecryptedBackToArray(): void
    {
        $service = new CrypterDatabaseService($this->createParameterBag());
        $iv = base64_encode(random_bytes(16));

        $encrypted = $service->encryptUserCredentialOrFail([
            'userName' => 'john.doe',
            'userPassword' => 'super-secret',
        ], $iv);

        $decrypted = $service->decryptUserCredentialOrFail($encrypted, $iv);

        self::assertSame(
            [
                'userName' => 'john.doe',
                'userPassword' => 'super-secret',
            ],
            $decrypted
        );
    }

    public function testEncryptUserCredentialPreservesLegacyEncryptedCredentialShape(): void
    {
        $service = new CrypterDatabaseService($this->createParameterBag());
        $iv = base64_encode(random_bytes(16));

        $encrypted = $service->enrcyptUserCredential([
            'userName' => 'john.doe',
            'userPassword' => 'super-secret',
        ], $iv);

        self::assertArrayHasKey('encryptedCredential', $encrypted);
        self::assertIsString($encrypted['encryptedCredential']);
    }

    public function testDecryptUserCredentialPreservesLegacyJsonStringShape(): void
    {
        $service = new CrypterDatabaseService($this->createParameterBag());
        $iv = base64_encode(random_bytes(16));
        $encrypted = $service->encryptUserCredentialOrFail([
            'userName' => 'john.doe',
            'userPassword' => 'super-secret',
        ], $iv);

        self::assertSame(
            '{"userName":"john.doe","userPassword":"super-secret"}',
            $service->decryptUserCredential($encrypted, $iv)
        );
    }

    public function testDecryptUserCredentialThrowsRuntimeExceptionForInvalidJson(): void
    {
        $service = new CrypterDatabaseService($this->createParameterBag());
        $reflection = new \ReflectionClass($service);
        $keyProperty = $reflection->getProperty('key');
        $keyProperty->setAccessible(true);
        $keyProperty->setValue($service, hash('sha256', '12345678901234567890123456789012', true));

        $ivBytes = random_bytes(16);
        $encrypted = openssl_encrypt('not-json', 'aes-256-cbc', hash('sha256', '12345678901234567890123456789012', true), 0, $ivBytes);
        self::assertNotFalse($encrypted);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('JSON decoding failed: Syntax error');

        $service->decryptUserCredentialOrFail(base64_encode($encrypted), base64_encode($ivBytes));
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
}

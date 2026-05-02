<?php

declare(strict_types=1);

namespace App\Tests\Service\Crypters;

use App\Service\Crypters\CrypterService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;

final class CrypterServiceTest extends TestCase
{
    public function testEncryptAndDecryptJsonRoundTripWithoutMutableState(): void
    {
        $service = new CrypterService($this->createParameterBag());
        $payload = ['domainProcessId' => 'process-123', 'publicId' => 'public-456'];

        $encrypted = $service->encrypt($payload);

        self::assertSame($payload, $service->decryptJson($encrypted));
    }

    public function testEncryptAndDecryptDtoRoundTrip(): void
    {
        $service = new CrypterService($this->createParameterBag());
        $payload = ['domainProcessId' => 'process-123', 'publicId' => 'public-456'];

        $service->setData($payload);
        $encrypted = $service->encryptData();

        $service->setData($encrypted, false);

        self::assertSame($payload, $service->decryptData(true));
    }

    public function testDecryptStoredJsonDataRoundTrip(): void
    {
        $service = new CrypterService($this->createParameterBag());
        $payload = ['domainProcessId' => 'process-123', 'publicId' => 'public-456'];

        $service->setData($service->encrypt($payload), false);

        self::assertSame($payload, $service->decryptStoredJsonData());
    }

    public function testDecryptDataThrowsRuntimeExceptionWhenJsonPayloadIsInvalid(): void
    {
        $service = new CrypterService($this->createParameterBag());
        $reflection = new \ReflectionClass($service);
        $keyProperty = $reflection->getProperty('key');
        $keyProperty->setAccessible(true);
        $key = $keyProperty->getValue($service);

        $iv = str_repeat('a', 16);
        $encrypted = openssl_encrypt('not-json', 'aes-256-cbc', $key, 0, $iv);
        self::assertNotFalse($encrypted);

        $service->setData(base64_encode($iv . $encrypted), false);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('JSON decoding failed: Syntax error');

        $service->decryptData(true);
    }

    public function testDecryptThrowsRuntimeExceptionWhenJsonPayloadIsInvalid(): void
    {
        $service = new CrypterService($this->createParameterBag());
        $reflection = new \ReflectionClass($service);
        $keyProperty = $reflection->getProperty('key');
        $keyProperty->setAccessible(true);
        $key = $keyProperty->getValue($service);

        $iv = str_repeat('a', 16);
        $encrypted = openssl_encrypt('not-json', 'aes-256-cbc', $key, 0, $iv);
        self::assertNotFalse($encrypted);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('JSON decoding failed: Syntax error');

        $service->decryptJson(base64_encode($iv . $encrypted));
    }

    private function createParameterBag(): ContainerBagInterface&MockObject
    {
        $params = $this->createMock(ContainerBagInterface::class);
        $params
            ->method('get')
            ->willReturnMap([
                ['DATA_HASH_SECRET', '12345678901234567890123456789012'],
            ]);

        return $params;
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Service\Crypters;

use App\Service\Crypters\SodiumService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class SodiumServiceTest extends TestCase
{
    public function testEncryptAndDecryptRoundTrip(): void
    {
        $service = new SodiumService($this->createMock(LoggerInterface::class));

        $encrypted = $service->sodiumEncrypt('secret-payload', 'shared-secret');

        self::assertSame('secret-payload', $service->sodiumDecrypt($encrypted, 'shared-secret'));
    }

    public function testSodiumDecryptTreatsMalformedBase64AsTooShortCiphertext(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('critical')->with('Encrypted data is too short');

        $service = new SodiumService($logger);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Encrypted data is too short');

        $service->sodiumDecrypt('%%%not-base64%%%', 'shared-secret');
    }

    public function testSodiumDecryptRejectsTooShortCiphertext(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('critical')->with('Encrypted data is too short');

        $service = new SodiumService($logger);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Encrypted data is too short');

        $service->sodiumDecrypt(base64_encode('short'), 'shared-secret');
    }
}
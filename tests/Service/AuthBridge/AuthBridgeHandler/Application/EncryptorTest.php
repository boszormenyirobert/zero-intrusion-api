<?php

declare(strict_types=1);

namespace App\Tests\Service\AuthBridge\AuthBridgeHandler\Application;

use App\Service\AuthBridge\AuthBridgeHandler\Application\Encryptor;
use App\Service\Crypters\CrypterDatabaseLoginService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Serializer\SerializerInterface;

final class EncryptorTest extends TestCase
{
    public function testEncryptSerializesApplicationsBeforeEncryption(): void
    {
        $serializer = $this->createMock(SerializerInterface::class);
        $serializer
            ->expects(self::once())
            ->method('serialize')
            ->with([
                ['application' => 'mail', 'decrypted' => 'secret'],
            ], 'json')
            ->willReturn('{"application":"mail"}');

        $crypter = $this->createMock(CrypterDatabaseLoginService::class);
        $crypter
            ->expects(self::once())
            ->method('encryptData')
            ->with('{"application":"mail"}', 'iv-value')
            ->willReturn('encrypted-payload');

        $service = new Encryptor($crypter, $serializer);

        self::assertSame(
            'encrypted-payload',
            $service->encrypt([
                ['application' => 'mail', 'decrypted' => 'secret'],
            ], 'iv-value')
        );
    }
}

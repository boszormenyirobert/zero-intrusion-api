<?php

declare(strict_types=1);

namespace App\Tests\Service\Payload;

use App\Service\Crypters\CrypterService;
use App\Service\Payload\EncryptedPayloadDecoder;
use App\Service\Payload\JsonPayloadDecoder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;

final class EncryptedPayloadDecoderTest extends TestCase
{
    public function testDecodeReturnsDecryptedArrayPayload(): void
    {
        $params = $this->createParameterBag();
        $crypter = new CrypterService($params);
        $crypter->setData(['business_create' => ['publicId' => 'public-1']]);
        $encryptedData = $crypter->encryptData();

        $decoder = new EncryptedPayloadDecoder($crypter, new JsonPayloadDecoder(), $this->createMock(LoggerInterface::class));

        self::assertSame(
            ['business_create' => ['publicId' => 'public-1']],
            $decoder->decodeOrFail(['zeroIntrusionProyApi' => $encryptedData])
        );
    }

    public function testDecodeOrFailThrowsForInvalidDecryptedJson(): void
    {
        $params = $this->createParameterBag();
        $crypter = new CrypterService($params);
        $crypter->setData('scalar-payload');
        $encryptedData = $crypter->encryptData();

        $decoder = new EncryptedPayloadDecoder($crypter, new JsonPayloadDecoder(), $this->createMock(LoggerInterface::class));

        $this->expectException(\UnexpectedValueException::class);
        $this->expectExceptionMessage('Invalid encrypted payload.');

        $decoder->decodeOrFail(['zeroIntrusionProyApi' => $encryptedData]);
    }

    private function createParameterBag(): ContainerBagInterface&MockObject
    {
        $params = $this->createMock(ContainerBagInterface::class);
        $params->method('get')->willReturnMap([
            ['DATA_HASH_SECRET', '12345678901234567890123456789012'],
            ['SERVICE_API_KEY', 'client-key'],
            ['SERVICE_API_SECRET', 'secret-key'],
        ]);

        return $params;
    }
}

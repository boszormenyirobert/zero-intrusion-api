<?php

declare(strict_types=1);

namespace App\Tests\Service\Device\Nfc;

use App\Service\Payload\JsonPayloadDecoder;
use App\Service\Device\Nfc\NfcDecryptRequestMapper;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class NfcDecryptRequestMapperTest extends TestCase
{
    public function testMapCreatesDecryptRequestDtoFromJsonStringPayload(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('error');

        $mapper = new NfcDecryptRequestMapper($logger, new JsonPayloadDecoder());
        $dto = $mapper->map([
            'api_nfc_decrypt' => json_encode([
                'userPublicId' => 'public-1',
                'publicId' => 'corp-1',
                'nfcData' => 'encrypted-payload',
            ], JSON_THROW_ON_ERROR),
        ]);

        self::assertSame('public-1', $dto->userPublicId);
        self::assertSame('corp-1', $dto->corporatePublicId);
        self::assertSame('encrypted-payload', $dto->nfcData);
    }

    public function testMapCreatesDecryptRequestDto(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('error');

        $mapper = new NfcDecryptRequestMapper($logger, new JsonPayloadDecoder());
        $dto = $mapper->map([
            'api_nfc_decrypt' => [
                'userPublicId' => 'public-1',
                'publicId' => 'corp-1',
                'nfcData' => 'encrypted-payload',
            ],
        ]);

        self::assertSame('public-1', $dto->userPublicId);
        self::assertSame('corp-1', $dto->corporatePublicId);
        self::assertSame('encrypted-payload', $dto->nfcData);
    }

    public function testMapRejectsInvalidPayload(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects(self::once())
            ->method('error')
            ->with('Invalid NFC decrypt payload.', ['payload_keys' => ['api_nfc_decrypt']]);

        $mapper = new NfcDecryptRequestMapper($logger, new JsonPayloadDecoder());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid NFC decrypt payload.');

        $mapper->map(['api_nfc_decrypt' => 'invalid']);
    }
}

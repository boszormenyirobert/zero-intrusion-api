<?php

declare(strict_types=1);

namespace App\Tests\Service\CredentialHub\OneTouch;

use App\Service\Payload\JsonPayloadDecoder;
use App\Service\CredentialHub\OneTouch\OneTouchQrIdentityRequestMapper;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class OneTouchQrIdentityRequestMapperTest extends TestCase
{
    public function testMapCreatesRequestDtoFromJsonStringPayload(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('error');

        $mapper = new OneTouchQrIdentityRequestMapper($logger, new JsonPayloadDecoder());
        $dto = $mapper->map([
            'one_touch_qr_identity' => json_encode(['type' => 'one-touch'], JSON_THROW_ON_ERROR),
        ]);

        self::assertSame('one-touch', $dto->type);
    }

    public function testMapCreatesRequestDto(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('error');

        $mapper = new OneTouchQrIdentityRequestMapper($logger, new JsonPayloadDecoder());
        $dto = $mapper->map([
            'one_touch_qr_identity' => ['type' => 'one-touch'],
        ]);

        self::assertSame('one-touch', $dto->type);
    }

    public function testMapRejectsInvalidPayload(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects(self::once())
            ->method('error')
            ->with('Invalid one-touch QR identity payload.', ['payload_keys' => ['one_touch_qr_identity']]);

        $mapper = new OneTouchQrIdentityRequestMapper($logger, new JsonPayloadDecoder());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid one-touch QR identity payload.');

        $mapper->map(['one_touch_qr_identity' => 'invalid']);
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Service\Device\Restore;

use App\Service\Payload\JsonPayloadDecoder;
use App\Service\Device\Restore\ReplaceDeviceRequestMapper;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class ReplaceDeviceRequestMapperTest extends TestCase
{
    public function testMapCreatesReplaceDeviceRequestDtoFromJsonStringPayload(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('error');

        $mapper = new ReplaceDeviceRequestMapper($logger, new JsonPayloadDecoder());
        $dto = $mapper->map([
            'replaceDevice' => json_encode([
                'email' => 'user@example.test',
                'phone' => '+3612345678',
            ], JSON_THROW_ON_ERROR),
        ]);

        self::assertSame('user@example.test', $dto->email);
        self::assertSame('+3612345678', $dto->phone);
    }

    public function testMapCreatesReplaceDeviceRequestDto(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('error');

        $mapper = new ReplaceDeviceRequestMapper($logger, new JsonPayloadDecoder());
        $dto = $mapper->map([
            'replaceDevice' => [
                'email' => 'user@example.test',
                'phone' => '+3612345678',
            ],
        ]);

        self::assertSame('user@example.test', $dto->email);
        self::assertSame('+3612345678', $dto->phone);
    }

    public function testMapRejectsInvalidPayload(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects(self::once())
            ->method('error')
            ->with('Invalid replace device payload.', ['payload_keys' => ['replaceDevice']]);

        $mapper = new ReplaceDeviceRequestMapper($logger, new JsonPayloadDecoder());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid replace device payload.');

        $mapper->map(['replaceDevice' => 'invalid']);
    }
}

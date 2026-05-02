<?php

declare(strict_types=1);

namespace App\Tests\Service\Device\Restore;

use App\Service\Payload\JsonPayloadDecoder;
use App\Service\Device\Restore\ReplaceDevicePinRequestMapper;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class ReplaceDevicePinRequestMapperTest extends TestCase
{
    public function testMapWrapsDecodedJsonRestorePinPayload(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('error');

        $mapper = new ReplaceDevicePinRequestMapper($logger, new JsonPayloadDecoder());
        $dto = $mapper->map([
            'restorePin' => json_encode(['replaceHash' => 'hash-1', 'data' => ['pin' => '1234']], JSON_THROW_ON_ERROR),
        ]);

        self::assertSame(['restorePin' => ['replaceHash' => 'hash-1', 'data' => ['pin' => '1234']]], $dto->toArray());
    }

    public function testMapWrapsValidatedPayload(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('error');

        $mapper = new ReplaceDevicePinRequestMapper($logger, new JsonPayloadDecoder());
        $dto = $mapper->map([
            'restorePin' => ['replaceHash' => 'hash-1', 'data' => ['pin' => '1234']],
        ]);

        self::assertSame(['restorePin' => ['replaceHash' => 'hash-1', 'data' => ['pin' => '1234']]], $dto->toArray());
    }

    public function testMapRejectsMissingRestorePin(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects(self::once())
            ->method('error')
            ->with('Invalid replace device pin payload.', ['payload_keys' => ['other']]);

        $mapper = new ReplaceDevicePinRequestMapper($logger, new JsonPayloadDecoder());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid replace device pin payload.');

        $mapper->map(['other' => true]);
    }
}

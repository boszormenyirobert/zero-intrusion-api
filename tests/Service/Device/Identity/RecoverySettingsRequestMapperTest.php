<?php

declare(strict_types=1);

namespace App\Tests\Service\Device\Identity;

use App\Service\Payload\JsonPayloadDecoder;
use App\Service\Device\Identity\RecoverySettingsRequestMapper;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class RecoverySettingsRequestMapperTest extends TestCase
{
    public function testMapCreatesRecoverySettingsRequestDtoFromJsonStringPayload(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('error');

        $mapper = new RecoverySettingsRequestMapper($logger, new JsonPayloadDecoder());
        $dto = $mapper->map([
            'recoverySettings' => json_encode([
                'publicId' => 'public-1',
                'privateId' => 'private-1',
                'email' => 'user@example.test',
                'phone' => '+3612345678',
                'privacyPolicy' => true,
                'fcmToken' => 'token-1',
            ], JSON_THROW_ON_ERROR),
        ]);

        self::assertSame('public-1', $dto->publicId);
        self::assertSame('private-1', $dto->privateId);
        self::assertSame('user@example.test', $dto->email);
    }

    public function testMapCreatesRecoverySettingsRequestDto(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('error');

        $mapper = new RecoverySettingsRequestMapper($logger, new JsonPayloadDecoder());
        $dto = $mapper->map([
            'recoverySettings' => [
                'publicId' => 'public-1',
                'privateId' => 'private-1',
                'email' => 'user@example.test',
                'phone' => '+3612345678',
                'privacyPolicy' => true,
                'fcmToken' => 'token-1',
            ],
        ]);

        self::assertSame('public-1', $dto->publicId);
        self::assertSame('private-1', $dto->privateId);
        self::assertSame('user@example.test', $dto->email);
    }

    public function testMapRejectsInvalidPayload(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects(self::once())
            ->method('error')
            ->with('Invalid recovery settings payload.', ['payload_keys' => ['recoverySettings']]);

        $mapper = new RecoverySettingsRequestMapper($logger, new JsonPayloadDecoder());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid recovery settings payload.');

        $mapper->map(['recoverySettings' => 'invalid']);
    }
}

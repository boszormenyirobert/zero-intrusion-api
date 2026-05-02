<?php

declare(strict_types=1);

namespace App\Tests\Service\Account;

use App\Service\Account\AccountRequestMapper;
use App\Service\Payload\JsonPayloadDecoder;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class AccountRequestMapperTest extends TestCase
{
    public function testMapCreatesAccountRequestDtoFromJsonStringPayload(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('error');

        $mapper = new AccountRequestMapper($logger, new JsonPayloadDecoder());
        $dto = $mapper->map([
            'get_registrated_business' => json_encode([
                'publicId' => 'public-1',
                'email' => 'user@example.test',
            ], JSON_THROW_ON_ERROR),
        ]);

        self::assertSame('public-1', $dto->publicId);
        self::assertSame('user@example.test', $dto->email);
    }

    public function testMapCreatesAccountRequestDto(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('error');

        $mapper = new AccountRequestMapper($logger, new JsonPayloadDecoder());
        $dto = $mapper->map([
            'get_registrated_business' => [
                'publicId' => 'public-1',
                'email' => 'user@example.test',
            ],
        ]);

        self::assertSame('public-1', $dto->publicId);
        self::assertSame('user@example.test', $dto->email);
    }

    public function testMapRejectsInvalidPayload(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects(self::once())
            ->method('error')
            ->with('Invalid account payload.', ['payload_keys' => ['get_registrated_business']]);

        $mapper = new AccountRequestMapper($logger, new JsonPayloadDecoder());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid account payload.');

        $mapper->map(['get_registrated_business' => 'invalid']);
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Service\User\Login;

use App\Service\Payload\JsonPayloadDecoder;
use App\Service\User\Login\LoginQrIdentityRequestMapper;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class LoginQrIdentityRequestMapperTest extends TestCase
{
    public function testMapCreatesRequestDtoFromJsonStringPayload(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects(self::never())
            ->method('error');

        $mapper = new LoginQrIdentityRequestMapper($logger, new JsonPayloadDecoder());
        $dto = $mapper->map([
            'user_login' => json_encode([
                'corporatePublicId' => 'corp-1',
                'corporateAuthentication' => 'signature',
                'domain' => 'https://example.test',
                'userPublicId' => 'user-1',
            ], JSON_THROW_ON_ERROR),
        ]);

        self::assertSame('corp-1', $dto->corporatePublicId);
        self::assertSame('signature', $dto->corporateAuthentication);
        self::assertSame('https://example.test', $dto->domain);
        self::assertSame('user-1', $dto->userPublicId);
    }

    public function testMapCreatesRequestDto(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects(self::never())
            ->method('error');

        $mapper = new LoginQrIdentityRequestMapper($logger, new JsonPayloadDecoder());
        $dto = $mapper->map([
            'user_login' => [
                'corporatePublicId' => 'corp-1',
                'corporateAuthentication' => 'signature',
                'domain' => 'https://example.test',
                'userPublicId' => 'user-1',
            ],
        ]);

        self::assertSame('corp-1', $dto->corporatePublicId);
        self::assertSame('signature', $dto->corporateAuthentication);
        self::assertSame('https://example.test', $dto->domain);
        self::assertSame('user-1', $dto->userPublicId);
        self::assertTrue($dto->hasUserPublicId());
    }

    public function testMapRejectsNonArrayPayload(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects(self::once())
            ->method('error')
            ->with('Invalid user login payload.', ['payload_keys' => ['user_login']]);

        $mapper = new LoginQrIdentityRequestMapper($logger, new JsonPayloadDecoder());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid user login payload.');

        $mapper->map(['user_login' => 'invalid']);
    }
}

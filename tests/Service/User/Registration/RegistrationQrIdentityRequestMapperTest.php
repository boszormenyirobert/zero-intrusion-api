<?php

declare(strict_types=1);

namespace App\Tests\Service\User\Registration;

use App\Service\Payload\JsonPayloadDecoder;
use App\Service\User\Registration\RegistrationQrIdentityRequestMapper;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class RegistrationQrIdentityRequestMapperTest extends TestCase
{
    public function testMapCreatesQrIdentityRequestDtoFromJsonStringPayload(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('error');

        $mapper = new RegistrationQrIdentityRequestMapper($logger, new JsonPayloadDecoder());
        $dto = $mapper->map([
            'user_registration' => json_encode(['corporatePublicId' => 'corp-1'], JSON_THROW_ON_ERROR),
        ]);

        self::assertSame(['corporatePublicId' => 'corp-1'], $dto->payload);
        self::assertSame('registrationProcessId', $dto->processKey);
    }

    public function testMapCreatesQrIdentityRequestDto(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('error');

        $mapper = new RegistrationQrIdentityRequestMapper($logger, new JsonPayloadDecoder());
        $dto = $mapper->map([
            'user_registration' => ['corporatePublicId' => 'corp-1'],
        ]);

        self::assertSame(['corporatePublicId' => 'corp-1'], $dto->payload);
        self::assertSame('registrationProcessId', $dto->processKey);
    }

    public function testMapRejectsInvalidPayload(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects(self::once())
            ->method('error')
            ->with('Invalid user registration payload.', ['payload_keys' => ['user_registration']]);

        $mapper = new RegistrationQrIdentityRequestMapper($logger, new JsonPayloadDecoder());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid user registration payload.');

        $mapper->map(['user_registration' => 'invalid']);
    }
}

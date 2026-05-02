<?php

declare(strict_types=1);

namespace App\Tests\Service\User\SecureDevice;

use App\Service\Payload\JsonPayloadDecoder;
use App\Service\User\SecureDevice\SecureDeviceQrIdentityRequestMapper;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class SecureDeviceQrIdentityRequestMapperTest extends TestCase
{
    public function testMapCreatesQrIdentityRequestDtoFromJsonStringPayload(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('error');

        $mapper = new SecureDeviceQrIdentityRequestMapper($logger, new JsonPayloadDecoder());
        $dto = $mapper->map([
            'secure_device_registration' => json_encode(['corporatePublicId' => 'corp-1'], JSON_THROW_ON_ERROR),
        ]);

        self::assertSame(['corporatePublicId' => 'corp-1'], $dto->payload);
        self::assertSame('domainProcessId', $dto->processKey);
    }

    public function testMapCreatesQrIdentityRequestDto(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('error');

        $mapper = new SecureDeviceQrIdentityRequestMapper($logger, new JsonPayloadDecoder());
        $dto = $mapper->map([
            'secure_device_registration' => ['corporatePublicId' => 'corp-1'],
        ]);

        self::assertSame(['corporatePublicId' => 'corp-1'], $dto->payload);
        self::assertSame('domainProcessId', $dto->processKey);
    }

    public function testMapRejectsInvalidPayload(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects(self::once())
            ->method('error')
            ->with('Invalid secure device registration payload.', ['payload_keys' => ['secure_device_registration']]);

        $mapper = new SecureDeviceQrIdentityRequestMapper($logger, new JsonPayloadDecoder());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid secure device registration payload.');

        $mapper->map(['secure_device_registration' => 'invalid']);
    }
}

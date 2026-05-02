<?php

declare(strict_types=1);

namespace App\Tests\Service\CredentialHub\Shared;

use App\DTO\CredentialHub\Shared\SharedRegistrationQrIdentityRequestDTO;
use App\Service\Payload\JsonPayloadDecoder;
use App\Service\CredentialHub\Shared\SharedRegistrationQrIdentityRequestMapper;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class SharedRegistrationQrIdentityRequestMapperTest extends TestCase
{
    public function testMapBuildsDtoFromJsonStringPayload(): void
    {
        $payload = [
            'type' => 'registration-domain',
            'userPublicId' => 'user-1',
            'registrationProcessId' => 'process-1',
        ];

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('error');

        $mapper = new SharedRegistrationQrIdentityRequestMapper($logger, new JsonPayloadDecoder());
        $dto = $mapper->map([
            'shared_registration_qr_identity' => json_encode($payload, JSON_THROW_ON_ERROR),
        ]);

        self::assertInstanceOf(SharedRegistrationQrIdentityRequestDTO::class, $dto);
        self::assertSame('registration-domain', $dto->type);
        self::assertSame('user-1', $dto->userPublicId);
        self::assertSame($payload, $dto->payload);
    }

    public function testMapRejectsInvalidJsonPayload(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects(self::once())
            ->method('error')
            ->with('Invalid shared registration QR identity payload.', [
                'payload_keys' => ['shared_registration_qr_identity'],
            ]);

        $mapper = new SharedRegistrationQrIdentityRequestMapper($logger, new JsonPayloadDecoder());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid shared registration QR identity payload.');

        $mapper->map([
            'shared_registration_qr_identity' => '{invalid-json',
        ]);
    }
}

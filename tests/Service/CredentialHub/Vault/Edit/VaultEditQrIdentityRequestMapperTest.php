<?php

declare(strict_types=1);

namespace App\Tests\Service\CredentialHub\Vault\Edit;

use App\Controller\CredentialHub\PayloadKeys;
use App\DTO\CredentialHub\Vault\Edit\VaultEditQrIdentityRequestDTO;
use App\Service\Payload\JsonPayloadDecoder;
use App\Service\CredentialHub\Vault\Edit\VaultEditQrIdentityRequestMapper;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class VaultEditQrIdentityRequestMapperTest extends TestCase
{
    public function testMapBuildsDtoFromJsonStringPayload(): void
    {
        $payload = [
            'userPublicId' => 'user-1',
            'registrationProcessId' => 'process-1',
        ];

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('error');

        $mapper = new VaultEditQrIdentityRequestMapper($logger, new JsonPayloadDecoder());
        $dto = $mapper->map([
            PayloadKeys::VAULT_EDIT_QR_IDENTITY => json_encode($payload, JSON_THROW_ON_ERROR),
        ]);

        self::assertInstanceOf(VaultEditQrIdentityRequestDTO::class, $dto);
        self::assertSame('user-1', $dto->userPublicId);
        self::assertSame($payload, $dto->payload);
    }

    public function testMapRejectsInvalidJsonPayload(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects(self::once())
            ->method('error')
            ->with('Invalid vault edit QR identity payload.', [
                'payload_keys' => [PayloadKeys::VAULT_EDIT_QR_IDENTITY],
            ]);

        $mapper = new VaultEditQrIdentityRequestMapper($logger, new JsonPayloadDecoder());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid vault edit QR identity payload.');

        $mapper->map([
            PayloadKeys::VAULT_EDIT_QR_IDENTITY => '{invalid-json',
        ]);
    }
}

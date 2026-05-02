<?php

declare(strict_types=1);

namespace App\Tests\Service\CredentialHub\Vault\Read;

use App\Controller\CredentialHub\PayloadKeys;
use App\DTO\CredentialHub\Vault\Read\VaultReadQrIdentityRequestDTO;
use App\Service\Payload\JsonPayloadDecoder;
use App\Service\CredentialHub\Vault\Read\VaultReadQrIdentityRequestMapper;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class VaultReadQrIdentityRequestMapperTest extends TestCase
{
    public function testMapBuildsDtoFromJsonStringPayload(): void
    {
        $payload = [
            'userPublicId' => 'user-1',
            'applicationProcessId' => 'process-1',
        ];

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('error');

        $mapper = new VaultReadQrIdentityRequestMapper($logger, new JsonPayloadDecoder());
        $dto = $mapper->map([
            PayloadKeys::VAULT_READ_QR_IDENTITY => json_encode($payload, JSON_THROW_ON_ERROR),
        ]);

        self::assertInstanceOf(VaultReadQrIdentityRequestDTO::class, $dto);
        self::assertSame('user-1', $dto->userPublicId);
        self::assertSame($payload, $dto->payload);
    }

    public function testMapRejectsInvalidJsonPayload(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects(self::once())
            ->method('error')
            ->with('Invalid vault read QR identity payload.', [
                'payload_keys' => [PayloadKeys::VAULT_READ_QR_IDENTITY],
            ]);

        $mapper = new VaultReadQrIdentityRequestMapper($logger, new JsonPayloadDecoder());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid vault read QR identity payload.');

        $mapper->map([
            PayloadKeys::VAULT_READ_QR_IDENTITY => '{invalid-json',
        ]);
    }
}

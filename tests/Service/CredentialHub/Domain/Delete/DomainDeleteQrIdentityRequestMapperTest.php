<?php

declare(strict_types=1);

namespace App\Tests\Service\CredentialHub\Domain\Delete;

use App\Controller\CredentialHub\PayloadKeys;
use App\DTO\CredentialHub\Domain\Delete\DomainDeleteQrIdentityRequestDTO;
use App\Service\Payload\JsonPayloadDecoder;
use App\Service\CredentialHub\Domain\Delete\DomainDeleteQrIdentityRequestMapper;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class DomainDeleteQrIdentityRequestMapperTest extends TestCase
{
    public function testMapBuildsDtoFromJsonStringPayload(): void
    {
        $payload = [
            'userPublicId' => 'user-1',
            'removeProcessId' => 'process-1',
        ];

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('error');

        $mapper = new DomainDeleteQrIdentityRequestMapper($logger, new JsonPayloadDecoder());
        $dto = $mapper->map([
            PayloadKeys::DOMAIN_DELETE_QR_IDENTITY => json_encode($payload, JSON_THROW_ON_ERROR),
        ]);

        self::assertInstanceOf(DomainDeleteQrIdentityRequestDTO::class, $dto);
        self::assertSame('user-1', $dto->userPublicId);
        self::assertNull($dto->domain);
        self::assertNull($dto->type);
    }

    public function testMapRejectsInvalidJsonPayload(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects(self::once())
            ->method('error')
            ->with('Invalid domain delete QR identity payload.', [
                'payload_keys' => [PayloadKeys::DOMAIN_DELETE_QR_IDENTITY],
            ]);

        $mapper = new DomainDeleteQrIdentityRequestMapper($logger, new JsonPayloadDecoder());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid domain delete QR identity payload.');

        $mapper->map([
            PayloadKeys::DOMAIN_DELETE_QR_IDENTITY => '{invalid-json',
        ]);
    }
}

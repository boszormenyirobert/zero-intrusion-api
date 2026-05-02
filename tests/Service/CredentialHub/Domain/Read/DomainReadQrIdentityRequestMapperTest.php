<?php

declare(strict_types=1);

namespace App\Tests\Service\CredentialHub\Domain\Read;

use App\Controller\CredentialHub\PayloadKeys;
use App\DTO\CredentialHub\Domain\Read\DomainReadQrIdentityRequestDTO;
use App\Service\Payload\JsonPayloadDecoder;
use App\Service\CredentialHub\Domain\Read\DomainReadQrIdentityRequestMapper;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class DomainReadQrIdentityRequestMapperTest extends TestCase
{
    public function testMapBuildsDtoFromJsonStringPayload(): void
    {
        $payload = [
            'userPublicId' => 'user-1',
            'domainProcessId' => 'process-1',
        ];

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('error');

        $mapper = new DomainReadQrIdentityRequestMapper($logger, new JsonPayloadDecoder());
        $dto = $mapper->map([
            PayloadKeys::DOMAIN_READ_QR_IDENTITY => json_encode($payload, JSON_THROW_ON_ERROR),
        ]);

        self::assertInstanceOf(DomainReadQrIdentityRequestDTO::class, $dto);
        self::assertSame('user-1', $dto->userPublicId);
        self::assertSame('process-1', $payload['domainProcessId']);
        self::assertNull($dto->domain);
    }

    public function testMapRejectsInvalidJsonPayload(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects(self::once())
            ->method('error')
            ->with('Invalid domain read QR identity payload.', [
                'payload_keys' => [PayloadKeys::DOMAIN_READ_QR_IDENTITY],
            ]);

        $mapper = new DomainReadQrIdentityRequestMapper($logger, new JsonPayloadDecoder());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid domain read QR identity payload.');

        $mapper->map([
            PayloadKeys::DOMAIN_READ_QR_IDENTITY => '{invalid-json',
        ]);
    }
}

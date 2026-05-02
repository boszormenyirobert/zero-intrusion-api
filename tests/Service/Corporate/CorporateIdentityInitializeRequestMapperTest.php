<?php

declare(strict_types=1);

namespace App\Tests\Service\Corporate;

use App\Service\Corporate\CorporateIdentityInitializeRequestMapper;
use App\Service\Payload\JsonPayloadDecoder;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class CorporateIdentityInitializeRequestMapperTest extends TestCase
{
    public function testMapCreatesRequestDto(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('error');

        $mapper = new CorporateIdentityInitializeRequestMapper($logger, new JsonPayloadDecoder());
        $dto = $mapper->map([
            'getIdentity' => json_encode([
                'publicId' => 'public-1',
                'scope' => 'external',
                'businessModel' => 'businessBasic',
            ], JSON_THROW_ON_ERROR),
        ]);

        self::assertSame('public-1', $dto->publicId);
        self::assertSame('external', $dto->scope);
        self::assertSame('businessBasic', $dto->businessModel);
    }

    public function testMapCreatesRequestDtoFromArrayPayload(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('error');

        $mapper = new CorporateIdentityInitializeRequestMapper($logger, new JsonPayloadDecoder());
        $dto = $mapper->map([
            'getIdentity' => [
                'publicId' => 'public-1',
                'scope' => 'external',
                'businessModel' => 'businessBasic',
            ],
        ]);

        self::assertSame('public-1', $dto->publicId);
        self::assertSame('external', $dto->scope);
        self::assertSame('businessBasic', $dto->businessModel);
    }

    public function testMapRejectsInvalidPayload(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects(self::once())
            ->method('error')
            ->with('Invalid corporate initialize payload.', ['payload_keys' => ['getIdentity']]);

        $mapper = new CorporateIdentityInitializeRequestMapper($logger, new JsonPayloadDecoder());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid corporate initialize payload.');

        $mapper->map(['getIdentity' => '']);
    }
}

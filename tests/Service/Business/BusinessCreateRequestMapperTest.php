<?php

declare(strict_types=1);

namespace App\Tests\Service\Business;

use App\Service\Business\BusinessCreateRequestMapper;
use App\Service\Payload\JsonPayloadDecoder;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class BusinessCreateRequestMapperTest extends TestCase
{
    public function testMapCreatesRequestDto(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects(self::never())
            ->method('error');

        $mapper = new BusinessCreateRequestMapper($logger, new JsonPayloadDecoder());
        $dto = $mapper->map([
            'business_create' => json_encode([
                'businessModel' => 'businessBasic',
                'publicId' => 'public-1',
                'scope' => 'external',
            ], JSON_THROW_ON_ERROR),
        ]);

        self::assertSame('businessBasic', $dto->businessModel);
        self::assertSame('public-1', $dto->publicId);
        self::assertSame('external', $dto->scope);
    }

    public function testMapCreatesRequestDtoFromArrayPayload(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects(self::never())
            ->method('error');

        $mapper = new BusinessCreateRequestMapper($logger, new JsonPayloadDecoder());
        $dto = $mapper->map([
            'business_create' => [
                'businessModel' => 'businessBasic',
                'publicId' => 'public-1',
                'scope' => 'external',
            ],
        ]);

        self::assertSame('businessBasic', $dto->businessModel);
        self::assertSame('public-1', $dto->publicId);
        self::assertSame('external', $dto->scope);
    }

    public function testMapRejectsInvalidPayload(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects(self::once())
            ->method('error')
            ->with('Invalid business create payload.', ['payload_keys' => ['business_create']]);

        $mapper = new BusinessCreateRequestMapper($logger, new JsonPayloadDecoder());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid business create payload.');

        $mapper->map(['business_create' => '']);
    }
}

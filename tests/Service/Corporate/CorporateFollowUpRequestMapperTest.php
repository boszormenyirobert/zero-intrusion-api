<?php

declare(strict_types=1);

namespace App\Tests\Service\Corporate;

use App\Service\Corporate\CorporateFollowUpRequestMapper;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class CorporateFollowUpRequestMapperTest extends TestCase
{
    public function testMapWrapsValidatedPayload(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('error');

        $mapper = new CorporateFollowUpRequestMapper($logger);
        $dto = $mapper->map([
            'updateIdentity' => ['corporateId' => 'corp-1'],
        ]);

        self::assertSame(['updateIdentity' => ['corporateId' => 'corp-1']], $dto->toArray());
    }

    public function testMapRejectsMissingUpdateIdentity(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects(self::once())
            ->method('error')
            ->with('Invalid corporate follow-up payload.', ['payload_keys' => ['other']]);

        $mapper = new CorporateFollowUpRequestMapper($logger);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid corporate follow-up payload.');

        $mapper->map(['other' => true]);
    }
}

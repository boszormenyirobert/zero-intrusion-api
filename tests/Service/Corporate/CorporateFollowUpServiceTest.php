<?php

declare(strict_types=1);

namespace App\Tests\Service\Corporate;

use App\DTO\Corporate\CorporateFollowUpRequestDTO;
use App\Entity\CorporateIdentity;
use App\Exception\CorporateRegistrationException;
use App\Service\Corporate\CorporateFollowUpService;
use App\Service\Corporate\CorporateRegistrationService;
use PHPUnit\Framework\TestCase;

final class CorporateFollowUpServiceTest extends TestCase
{
    public function testHandleReturnsSuccessResult(): void
    {
        $request = new CorporateFollowUpRequestDTO([
            'updateIdentity' => ['corporateId' => 'corp-1'],
        ]);

        $corporateRegistrationService = $this->createMock(CorporateRegistrationService::class);
        $corporateRegistrationService
            ->expects(self::once())
            ->method('updateSubscriptionDataOrFail')
            ->with($request->toArray())
            ->willReturn(new CorporateIdentity());

        $service = new CorporateFollowUpService($corporateRegistrationService);
        $result = $service->handle($request);

        self::assertTrue($result->successful);
        self::assertNull($result->errorPayload);
    }

    public function testHandleReturnsErrorResultWhenServiceThrowsCorporateRegistrationException(): void
    {
        $request = new CorporateFollowUpRequestDTO([
            'updateIdentity' => ['corporateId' => 'corp-1'],
        ]);
        $errorPayload = ['error' => true, 'message' => 'CorporateId missing'];

        $corporateRegistrationService = $this->createMock(CorporateRegistrationService::class);
        $corporateRegistrationService
            ->expects(self::once())
            ->method('updateSubscriptionDataOrFail')
            ->with($request->toArray())
            ->willThrowException(new CorporateRegistrationException('CorporateId missing'));

        $service = new CorporateFollowUpService($corporateRegistrationService);
        $result = $service->handle($request);

        self::assertFalse($result->successful);
        self::assertSame($errorPayload, $result->errorPayload);
    }
}

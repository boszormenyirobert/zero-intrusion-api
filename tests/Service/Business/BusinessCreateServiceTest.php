<?php

declare(strict_types=1);

namespace App\Tests\Service\Business;

use App\DTO\Business\BusinessCreateRequestDTO;
use App\Service\Business\BusinessCreateService;
use App\Service\Corporate\CorporateRegistrationService;
use PHPUnit\Framework\TestCase;

final class BusinessCreateServiceTest extends TestCase
{
    public function testHandleReturnsResponseDto(): void
    {
        $request = new BusinessCreateRequestDTO('businessPlus', 'public-1', 'external');

        $corporateRegistrationService = $this->createMock(CorporateRegistrationService::class);
        $corporateRegistrationService
            ->expects(self::once())
            ->method('getBusinessRegistration')
            ->with($request->toArray())
            ->willReturn([
                'body' => 'encrypted-body',
                'headers' => ['X-Test' => 'header'],
            ]);

        $service = new BusinessCreateService($corporateRegistrationService);
        $result = $service->handle($request);

        self::assertSame('encrypted-body', $result->body);
        self::assertSame(['X-Test' => 'header'], $result->headers);
    }
}

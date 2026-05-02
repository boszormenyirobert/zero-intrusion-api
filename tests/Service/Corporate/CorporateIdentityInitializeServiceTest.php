<?php

declare(strict_types=1);

namespace App\Tests\Service\Corporate;

use App\DTO\Corporate\CorporateIdentityInitializeRequestDTO;
use App\Entity\BusinessServices;
use App\Entity\Identity;
use App\Repository\IdentityRepository;
use App\Service\Corporate\CorporateIdentityInitializeService;
use App\Service\Corporate\CorporateRegistrationService;
use PHPUnit\Framework\TestCase;

final class CorporateIdentityInitializeServiceTest extends TestCase
{
    public function testHandleUsesPayloadBusinessModelForInternalScope(): void
    {
        $request = new CorporateIdentityInitializeRequestDTO('public-1', 'internal', 'businessBasic');

        $corporateRegistrationService = $this->createMock(CorporateRegistrationService::class);
        $corporateRegistrationService
            ->expects(self::once())
            ->method('getSubscriptionData')
            ->with($request->toArray())
            ->willReturn([
                'body' => 'encrypted-body',
                'headers' => ['X-Test' => 'header'],
            ]);
        $corporateRegistrationService
            ->expects(self::never())
            ->method('getSelectedSubscription');

        $identityRepository = $this->createMock(IdentityRepository::class);
        $identityRepository
            ->expects(self::never())
            ->method('findOneBy');

        $service = new CorporateIdentityInitializeService($corporateRegistrationService, $identityRepository);
        $result = $service->handle($request);

        self::assertSame('encrypted-body', $result->body);
        self::assertSame(['X-Test' => 'header'], $result->headers);
    }

    public function testHandleResolvesBusinessModelForExternalScope(): void
    {
        $request = new CorporateIdentityInitializeRequestDTO('public-1', 'external', null);
        $businessService = new BusinessServices();
        $identity = (new Identity())->setBusinessService($businessService);

        $corporateRegistrationService = $this->createMock(CorporateRegistrationService::class);
        $corporateRegistrationService
            ->expects(self::once())
            ->method('getSelectedSubscription')
            ->with($businessService)
            ->willReturn('businessPro');
        $corporateRegistrationService
            ->expects(self::once())
            ->method('getSubscriptionData')
            ->with([
                'publicId' => 'public-1',
                'scope' => 'external',
                'businessModel' => 'businessPro',
            ])
            ->willReturn([
                'body' => 'encrypted-body',
                'headers' => ['X-Test' => 'header'],
            ]);

        $identityRepository = $this->createMock(IdentityRepository::class);
        $identityRepository
            ->expects(self::once())
            ->method('findOneBy')
            ->with(['publicId' => 'public-1'])
            ->willReturn($identity);

        $service = new CorporateIdentityInitializeService($corporateRegistrationService, $identityRepository);
        $result = $service->handle($request);

        self::assertSame('encrypted-body', $result->body);
        self::assertSame(['X-Test' => 'header'], $result->headers);
    }
}

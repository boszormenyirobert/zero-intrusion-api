<?php

declare(strict_types=1);

namespace App\Tests\Service\Corporate;

use App\Entity\BusinessServices;
use App\Service\Corporate\CorporateAuthorizedResponseFactory;
use App\Service\Corporate\CorporateBusinessRegistrationHandler;
use App\Service\Corporate\CorporateRegistrationDatabaseService;
use PHPUnit\Framework\TestCase;

final class CorporateBusinessRegistrationHandlerTest extends TestCase
{
    public function testHandleDelegatesPersistenceAndResponseBuilding(): void
    {
        $businessServices = (new BusinessServices())
            ->setPro(false)
            ->setPlus(true)
            ->setBasic(false)
            ->setBiometric(false)
            ->setPasswordManager(false);

        $databaseService = $this->createMock(CorporateRegistrationDatabaseService::class);
        $databaseService
            ->expects(self::once())
            ->method('generateBusinessService')
            ->with('businessPlus')
            ->willReturn($businessServices);
        $databaseService
            ->expects(self::once())
            ->method('updateUserIdentity')
            ->with('public-1', $businessServices);

        $responseFactory = $this->createMock(CorporateAuthorizedResponseFactory::class);
        $responseFactory
            ->expects(self::once())
            ->method('create')
            ->with((array) $businessServices)
            ->willReturn(['headers' => ['X-Auth' => 'token'], 'body' => 'encrypted']);

        $handler = new CorporateBusinessRegistrationHandler($databaseService, $responseFactory);

        self::assertSame([
            'headers' => ['X-Auth' => 'token'],
            'body' => 'encrypted',
        ], $handler->handle([
            'businessModel' => 'businessPlus',
            'publicId' => 'public-1',
        ]));
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Service\Corporate;

use App\Service\Corporate\CorporateAuthorizedResponseFactory;
use App\Service\Corporate\CorporateRegistrationDatabaseService;
use App\Service\Corporate\CorporateSubscriptionInitializationHandler;
use App\Service\Corporate\IdentityService;
use PHPUnit\Framework\TestCase;

final class CorporateSubscriptionInitializationHandlerTest extends TestCase
{
    public function testHandleInitializesIdentityPersistsRelationAndBuildsResponse(): void
    {
        $identity = [
            'corporate_id' => 'corp-1',
            'scope' => 'internal',
        ];

        $identityService = $this->createMock(IdentityService::class);
        $identityService
            ->expects(self::once())
            ->method('initializeIdentity')
            ->with('businessBasic', 'public-1', 'internal');
        $identityService
            ->expects(self::once())
            ->method('getIdentity')
            ->willReturn($identity);

        $databaseService = $this->createMock(CorporateRegistrationDatabaseService::class);
        $databaseService
            ->expects(self::once())
            ->method('createUserCorporateRelation')
            ->with('public-1', 'corp-1');

        $responseFactory = $this->createMock(CorporateAuthorizedResponseFactory::class);
        $responseFactory
            ->expects(self::once())
            ->method('create')
            ->with($identity)
            ->willReturn(['headers' => ['X-Auth' => 'token'], 'body' => 'encrypted']);

        $handler = new CorporateSubscriptionInitializationHandler($identityService, $databaseService, $responseFactory);

        self::assertSame([
            'headers' => ['X-Auth' => 'token'],
            'body' => 'encrypted',
        ], $handler->handle([
            'publicId' => 'public-1',
            'scope' => 'internal',
            'businessModel' => 'businessBasic',
        ]));
    }
}

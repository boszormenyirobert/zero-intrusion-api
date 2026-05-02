<?php

declare(strict_types=1);

namespace App\Tests\Service\Corporate;

use App\Entity\CorporateIdentity;
use App\Exception\CorporateRegistrationException;
use App\Repository\CorporateIdentityRepository;
use App\Service\Corporate\CorporateRegistrationDatabaseService;
use App\Service\Corporate\CorporateFollowUpUpdater;
use PHPUnit\Framework\TestCase;

final class CorporateFollowUpUpdaterTest extends TestCase
{
    public function testHandleThrowsExceptionWhenCorporateIdIsMissing(): void
    {
        $repository = $this->createMock(CorporateIdentityRepository::class);
        $databaseService = $this->createMock(CorporateRegistrationDatabaseService::class);
        $updater = new CorporateFollowUpUpdater($repository, $databaseService);

        $this->expectException(CorporateRegistrationException::class);
        $this->expectExceptionMessage('CorporateId missing in the follow-up data');

        $updater->handle(['updateIdentity' => []]);
    }

    public function testHandleThrowsExceptionWhenCorporateCannotBeResolved(): void
    {
        $repository = $this->createMock(CorporateIdentityRepository::class);
        $databaseService = $this->createMock(CorporateRegistrationDatabaseService::class);
        $updater = new CorporateFollowUpUpdater($repository, $databaseService);

        $repository
            ->expects(self::once())
            ->method('findOneBy')
            ->with(['corporateId' => 'corp-1'])
            ->willReturn(null);

        $this->expectException(CorporateRegistrationException::class);
        $this->expectExceptionMessage('CorporateId is not registrated in the database');

        $updater->handle(['updateIdentity' => ['corporateId' => 'corp-1']]);
    }

    public function testHandleThrowsExceptionWhenPersistenceFails(): void
    {
        $corporate = (new CorporateIdentity())
            ->setCorporateId('corp-1')
            ->setIv(base64_encode(random_bytes(16)))
            ->setCorporateIdKey('key')
            ->setCorporateIdSecret('secret')
            ->setSslPrivateKey('ssl');

        $repository = $this->createMock(CorporateIdentityRepository::class);
        $repository
            ->expects(self::once())
            ->method('findOneBy')
            ->with(['corporateId' => 'corp-1'])
            ->willReturn($corporate);

        $databaseService = $this->createMock(CorporateRegistrationDatabaseService::class);
        $databaseService
            ->expects(self::once())
            ->method('addFollowUpData')
            ->with($corporate, ['updateIdentity' => ['corporateId' => 'corp-1']])
            ->willThrowException(new \RuntimeException('Corporate not saved in database'));

        $updater = new CorporateFollowUpUpdater($repository, $databaseService);

        $this->expectException(CorporateRegistrationException::class);
        $this->expectExceptionMessage('Corporate not saved in database');

        $updater->handle(['updateIdentity' => ['corporateId' => 'corp-1']]);
    }
}

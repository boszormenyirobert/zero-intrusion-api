<?php

declare(strict_types=1);

namespace App\Tests\Service\Corporate;

use App\Entity\BusinessServices;
use App\Entity\CorporateIdentity;
use App\Entity\Identity;
use App\Repository\IdentityRepository;
use App\Service\Corporate\CorporateRegistrationDatabaseService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class CorporateRegistrationDatabaseServiceTest extends TestCase
{
    public function testGenerateBusinessServiceCreatesExpectedBusinessProFlags(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist')->with(self::isInstanceOf(BusinessServices::class));
        $entityManager->expects(self::once())->method('flush');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::exactly(2))->method('info');

        $identityRepository = $this->createMock(IdentityRepository::class);

        $service = new CorporateRegistrationDatabaseService($entityManager, $logger, $identityRepository);
        $businessServices = $service->generateBusinessService('businessPro');

        self::assertTrue($businessServices->isPro());
        self::assertFalse($businessServices->isPlus());
        self::assertFalse($businessServices->isBasic());
        self::assertFalse($businessServices->isBiometric());
        self::assertFalse($businessServices->isPasswordManager());
    }

    public function testAddNewIdentityForInternalScopeGeneratesBusinessServiceAndUpdatesIdentity(): void
    {
        $corporateIdentity = (new CorporateIdentity())->setCorporateId('corp-1')->setIv(base64_encode(random_bytes(16)))->setCorporateIdKey('key')->setCorporateIdSecret('secret')->setSslPrivateKey('ssl');
        $businessServices = (new BusinessServices())
            ->setPro(false)
            ->setPlus(true)
            ->setBasic(false)
            ->setBiometric(false)
            ->setPasswordManager(false);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist')->with($corporateIdentity);
        $entityManager->expects(self::once())->method('flush');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::atLeastOnce())->method('info');

        $identityRepository = $this->createMock(IdentityRepository::class);

        $service = $this->getMockBuilder(CorporateRegistrationDatabaseService::class)
            ->setConstructorArgs([$entityManager, $logger, $identityRepository])
            ->onlyMethods(['generateBusinessService', 'updateUserIdentity'])
            ->getMock();

        $service->expects(self::once())->method('generateBusinessService')->with('businessPlus')->willReturn($businessServices);
        $service->expects(self::once())->method('updateUserIdentity')->with('public-1', $businessServices);

        $service->addNewIdentity($corporateIdentity, 'businessPlus', 'public-1', 'internal');

        self::assertSame($businessServices, $corporateIdentity->getBusinessServices());
    }

    public function testAddNewIdentityForExternalScopeLinksExistingBusinessService(): void
    {
        $corporateIdentity = (new CorporateIdentity())->setCorporateId('corp-1')->setIv(base64_encode(random_bytes(16)))->setCorporateIdKey('key')->setCorporateIdSecret('secret')->setSslPrivateKey('ssl');
        $businessServices = (new BusinessServices())
            ->setPro(false)
            ->setPlus(false)
            ->setBasic(true)
            ->setBiometric(false)
            ->setPasswordManager(false);
        $identity = (new Identity())->setPublicId('public-2')->setBusinessService($businessServices);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist')->with($corporateIdentity);
        $entityManager->expects(self::once())->method('flush');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::atLeastOnce())->method('info');

        $identityRepository = $this->createMock(IdentityRepository::class);
        $identityRepository
            ->expects(self::once())
            ->method('findOneBy')
            ->with(['publicId' => 'public-2'])
            ->willReturn($identity);

        $service = new CorporateRegistrationDatabaseService($entityManager, $logger, $identityRepository);
        $service->addNewIdentity($corporateIdentity, 'businessBasic', 'public-2', 'external');

        self::assertSame($businessServices, $corporateIdentity->getBusinessServices());
    }

    public function testUpdateUserIdentityPersistsUpdatedIdentity(): void
    {
        $businessServices = (new BusinessServices())
            ->setPro(false)
            ->setPlus(false)
            ->setBasic(false)
            ->setBiometric(true)
            ->setPasswordManager(false);
        $identity = (new Identity())->setPublicId('public-3');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist')->with($identity);
        $entityManager->expects(self::once())->method('flush');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::atLeastOnce())->method('info');
        $logger->expects(self::never())->method('critical');

        $identityRepository = $this->createMock(IdentityRepository::class);
        $identityRepository
            ->expects(self::once())
            ->method('findOneBy')
            ->with(['publicId' => 'public-3'])
            ->willReturn($identity);

        $service = new CorporateRegistrationDatabaseService($entityManager, $logger, $identityRepository);
        $service->updateUserIdentity('public-3', $businessServices);

        self::assertSame($businessServices, $identity->getBusinessService());
    }

    public function testUpdateUserIdentityThrowsRuntimeExceptionWhenIdentityIsMissing(): void
    {
        $businessServices = (new BusinessServices())
            ->setPro(true)
            ->setPlus(false)
            ->setBasic(false)
            ->setBiometric(false)
            ->setPasswordManager(false);

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('persist');
        $entityManager->expects(self::never())->method('flush');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::atLeastOnce())->method('info');
        $logger->expects(self::once())->method('critical');

        $identityRepository = $this->createMock(IdentityRepository::class);
        $identityRepository
            ->expects(self::once())
            ->method('findOneBy')
            ->with(['publicId' => 'missing-public-id'])
            ->willReturn(null);

        $service = new CorporateRegistrationDatabaseService($entityManager, $logger, $identityRepository);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Identity not found.');

        $service->updateUserIdentity('missing-public-id', $businessServices);
    }
}
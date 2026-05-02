<?php

declare(strict_types=1);

namespace App\Tests\Service\Corporate;

use App\Entity\CorporateIdentity;
use App\Repository\IdentityRepository;
use App\Service\Corporate\CorporateRegistrationDatabaseService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class CorporateRegistrationDatabaseServiceAdditionalTest extends TestCase
{
    public function testAddFollowUpDataUpdatesEntityAndFlushes(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush');

        $service = new CorporateRegistrationDatabaseService(
            $entityManager,
            $this->createMock(LoggerInterface::class),
            $this->createMock(IdentityRepository::class),
        );

        $corporate = new CorporateIdentity();
        $result = $service->addFollowUpData($corporate, [
            'updateIdentity' => [
                'callbackUserLogin' => 'https://example.test/login',
                'callbackUserRegistration' => 'https://example.test/register',
                'domain' => 'example.test',
            ],
        ]);

        self::assertSame($corporate, $result);
        self::assertSame('https://example.test/login', $corporate->getCallbackUserLogin());
        self::assertSame('https://example.test/register', $corporate->getCallbackUserRegistration());
        self::assertSame('example.test', $corporate->getDomain());
    }

    public function testAddFollowUpDataThrowsRuntimeExceptionWhenFlushFails(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('flush')->willThrowException(new \RuntimeException('db error'));

        $service = new CorporateRegistrationDatabaseService(
            $entityManager,
            $this->createMock(LoggerInterface::class),
            $this->createMock(IdentityRepository::class),
        );

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Corporate not saved in database');

        $service->addFollowUpData(new CorporateIdentity(), [
            'updateIdentity' => [
                'callbackUserLogin' => 'https://example.test/login',
                'callbackUserRegistration' => 'https://example.test/register',
                'domain' => 'example.test',
            ],
        ]);
    }

    public function testCreateUserCorporateRelationPersistsRelationEntity(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->expects(self::once())
            ->method('persist')
            ->with(self::callback(static fn ($entity): bool => $entity->getPublicId() === 'public-1' && $entity->getCorporateId() === 'corp-1'));
        $entityManager->expects(self::once())->method('flush');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::exactly(2))->method('info');

        $service = new CorporateRegistrationDatabaseService(
            $entityManager,
            $logger,
            $this->createMock(IdentityRepository::class),
        );

        $service->createUserCorporateRelation('public-1', 'corp-1');
        self::assertTrue(true);
    }
}
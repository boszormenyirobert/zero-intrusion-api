<?php

declare(strict_types=1);

namespace App\Tests\Service\Corporate;

use App\Entity\BusinessServices;
use App\Exception\CorporateRegistrationException;
use App\Repository\BusinessServicesRepository;
use App\Repository\CorporateIdentityRepository;
use App\Service\Corporate\CorporateAuthorizedResponseFactory;
use App\Service\Corporate\CorporateRegistrationDatabaseService;
use App\Service\Corporate\CorporateRegistrationService;
use App\Service\Corporate\IdentityService;
use App\Service\Crypters\CrypterService;
use App\Service\Shared\RequestService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;

final class CorporateRegistrationServiceAdditionalTest extends TestCase
{
    public function testUpdateSubscriptionDataOrFailThrowsExpectedExceptions(): void
    {
        $service = $this->createService();

        try {
            $service->updateSubscriptionDataOrFail(['updateIdentity' => []]);
            self::fail('Expected CorporateRegistrationException for missing corporateId.');
        } catch (CorporateRegistrationException $exception) {
            self::assertSame('CorporateId missing in the follow-up data', $exception->getMessage());
        }

        $corporateIdentityRepository = $this->createMock(CorporateIdentityRepository::class);
        $corporateIdentityRepository->expects(self::once())->method('findOneBy')->with(['corporateId' => 'corp-1'])->willReturn(null);

        $service = $this->createService(corporateIdentityRepository: $corporateIdentityRepository);

        try {
            $service->updateSubscriptionDataOrFail(['updateIdentity' => ['corporateId' => 'corp-1']]);
            self::fail('Expected CorporateRegistrationException for unknown corporateId.');
        } catch (CorporateRegistrationException $exception) {
            self::assertSame('CorporateId is not registrated in the database', $exception->getMessage());
        }
    }

    public function testUpdateSubscriptionDataReturnsExpectedErrorPayloads(): void
    {
        $service = $this->createService();

        self::assertSame([
            'error' => true,
            'message' => 'CorporateId missing in the follow-up data',
        ], $service->updateSubscriptionData(['updateIdentity' => []]));

        $corporateIdentityRepository = $this->createMock(CorporateIdentityRepository::class);
        $corporateIdentityRepository->expects(self::once())->method('findOneBy')->with(['corporateId' => 'corp-1'])->willReturn(null);

        $service = $this->createService(corporateIdentityRepository: $corporateIdentityRepository);

        self::assertSame([
            'error' => true,
            'message' => 'CorporateId is not registrated in the database',
        ], $service->updateSubscriptionData(['updateIdentity' => ['corporateId' => 'corp-1']]));
    }

    public function testGetSelectedSubscriptionReturnsNullForUnknownIdsOrFlags(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::exactly(2))->method('warning');
        $logger->expects(self::exactly(2))->method('info');

        $repository = $this->createMock(BusinessServicesRepository::class);
        $repository->expects(self::once())->method('findOneBy')->with(['id' => 999])->willReturn(null);

        $service = $this->createService(logger: $logger, businessServicesRepository: $repository);

        self::assertNull($service->getSelectedSubscription(999));
        self::assertNull($service->getSelectedSubscription((new BusinessServices())
            ->setPro(false)
            ->setPlus(false)
            ->setBasic(false)
            ->setBiometric(false)
            ->setPasswordManager(false)));
    }

    public function testAccessDataByKeyRejectsInvalidSegmentTypes(): void
    {
        $requestService = $this->createMock(RequestService::class);
        $requestService
            ->method('validPayload')
            ->willReturnOnConsecutiveCalls(
                ['updateIdentity' => ['not-string']],
                ['updateIdentity' => '"scalar"'],
            );

        $service = $this->createService(requestService: $requestService, logger: $this->createMock(LoggerInterface::class));

        try {
            $service->accessDataByKey(['payload' => 'ignored'], 'updateIdentity');
            self::fail('Expected InvalidArgumentException for non-string segment.');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame('Payload segment "updateIdentity" must be a non-empty JSON string.', $exception->getMessage());
        }

        try {
            $service->accessDataByKey(['payload' => 'ignored'], 'updateIdentity');
            self::fail('Expected InvalidArgumentException for scalar JSON segment.');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame('Payload segment "updateIdentity" must decode to an array.', $exception->getMessage());
        }
    }

    private function createService(
        ?LoggerInterface $logger = null,
        ?RequestService $requestService = null,
        ?BusinessServicesRepository $businessServicesRepository = null,
        ?CorporateIdentityRepository $corporateIdentityRepository = null,
    ): CorporateRegistrationService {
        $params = $this->createMock(ContainerBagInterface::class);
        $params->method('get')->willReturnMap([
            ['DATA_HASH_SECRET', 'test-data-hash-secret'],
        ]);

        return new CorporateRegistrationService(
            $params,
            $this->createMock(CorporateRegistrationDatabaseService::class),
            $this->createMock(IdentityService::class),
            $corporateIdentityRepository ?? $this->createMock(CorporateIdentityRepository::class),
            new CrypterService($params),
            $logger ?? $this->createMock(LoggerInterface::class),
            $requestService ?? $this->createMock(RequestService::class),
            $businessServicesRepository ?? $this->createMock(BusinessServicesRepository::class),
            $this->createMock(CorporateAuthorizedResponseFactory::class),
        );
    }
}
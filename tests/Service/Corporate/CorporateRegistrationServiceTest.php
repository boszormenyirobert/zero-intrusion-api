<?php

declare(strict_types=1);

namespace App\Tests\Service\Corporate;

use App\Entity\BusinessServices;
use App\Entity\CorporateIdentity;
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

final class CorporateRegistrationServiceTest extends TestCase
{
    public function testGetSelectedSubscriptionReturnsBusinessModelFromEntity(): void
    {
        $businessService = (new BusinessServices())
            ->setPro(false)
            ->setPlus(true)
            ->setBasic(false)
            ->setBiometric(false)
            ->setPasswordManager(false);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::exactly(2))->method('info');

        $service = $this->createService(logger: $logger);

        self::assertSame('businessPlus', $service->getSelectedSubscription($businessService));
    }

    public function testGetSelectedSubscriptionLoadsEntityWhenIdIsProvided(): void
    {
        $businessService = (new BusinessServices())
            ->setPro(false)
            ->setPlus(false)
            ->setBasic(false)
            ->setBiometric(false)
            ->setPasswordManager(true);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::exactly(2))->method('info');

        $businessServicesRepository = $this->createMock(BusinessServicesRepository::class);
        $businessServicesRepository
            ->expects(self::once())
            ->method('findOneBy')
            ->with(['id' => 9])
            ->willReturn($businessService);

        $service = $this->createService(logger: $logger, businessServicesRepository: $businessServicesRepository);

        self::assertSame('passwordManager', $service->getSelectedSubscription(9));
    }

    public function testAccessDataByKeyReturnsDecodedPayloadSegment(): void
    {
        $payload = ['updateIdentity' => '{"publicId":"public-1","scope":"external"}'];

        $requestService = $this->createMock(RequestService::class);
        $requestService
            ->expects(self::once())
            ->method('validPayload')
            ->with($payload)
            ->willReturn($payload);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::exactly(2))->method('info');

        $service = $this->createService(logger: $logger, requestService: $requestService);

        self::assertSame([
            'publicId' => 'public-1',
            'scope' => 'external',
        ], $service->accessDataByKey($payload, 'updateIdentity'));
    }

    public function testAccessDataByKeyThrowsForInvalidJsonPayloadSegment(): void
    {
        $payload = ['updateIdentity' => '{invalid'];

        $requestService = $this->createMock(RequestService::class);
        $requestService
            ->expects(self::once())
            ->method('validPayload')
            ->with($payload)
            ->willReturn($payload);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('info');

        $service = $this->createService(logger: $logger, requestService: $requestService);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Payload segment "updateIdentity" must contain valid JSON.');

        $service->accessDataByKey($payload, 'updateIdentity');
    }

    public function testUpdateSubscriptionDataDelegatesFollowUpPersistenceWhenCorporateExists(): void
    {
        $followUpPayload = [
            'updateIdentity' => [
                'corporateId' => 'corp-1',
            ],
        ];
        $corporate = (new CorporateIdentity())
            ->setCorporateId('corp-1')
            ->setIv(base64_encode(random_bytes(16)))
            ->setCorporateIdKey('key')
            ->setCorporateIdSecret('secret')
            ->setSslPrivateKey('ssl');

        $corporateIdentityRepository = $this->createMock(CorporateIdentityRepository::class);
        $corporateIdentityRepository
            ->expects(self::once())
            ->method('findOneBy')
            ->with(['corporateId' => 'corp-1'])
            ->willReturn($corporate);

        $databaseService = $this->createMock(CorporateRegistrationDatabaseService::class);
        $databaseService
            ->expects(self::once())
            ->method('addFollowUpData')
            ->with($corporate, $followUpPayload)
            ->willReturn($corporate);

        $service = $this->createService(
            corporateIdentityRepository: $corporateIdentityRepository,
            corporateRegistrationDatabaseService: $databaseService,
        );

        self::assertSame($corporate, $service->updateSubscriptionData($followUpPayload));
    }

    public function testGetBusinessRegistrationDelegatesAuthorizedResponseBuilding(): void
    {
        $subscriptionPayload = (new BusinessServices())
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
            ->willReturn($subscriptionPayload);
        $databaseService
            ->expects(self::once())
            ->method('updateUserIdentity')
            ->with('public-1', $subscriptionPayload);

        $responseFactory = $this->createMock(CorporateAuthorizedResponseFactory::class);
        $responseFactory
            ->expects(self::once())
            ->method('create')
            ->with((array) $subscriptionPayload)
            ->willReturn(['headers' => ['X-Auth' => 'token'], 'body' => 'encrypted']);

        $service = $this->createService(
            corporateRegistrationDatabaseService: $databaseService,
            corporateAuthorizedResponseFactory: $responseFactory,
        );

        self::assertSame(
            ['headers' => ['X-Auth' => 'token'], 'body' => 'encrypted'],
            $service->getBusinessRegistration([
                'businessModel' => 'businessPlus',
                'publicId' => 'public-1',
            ])
        );
    }

    public function testGetSubscriptionDataDelegatesAuthorizedResponseBuilding(): void
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

        $service = $this->createService(
            identityService: $identityService,
            corporateRegistrationDatabaseService: $databaseService,
            corporateAuthorizedResponseFactory: $responseFactory,
        );

        self::assertSame(
            ['headers' => ['X-Auth' => 'token'], 'body' => 'encrypted'],
            $service->getSubscriptionData([
                'publicId' => 'public-1',
                'scope' => 'internal',
                'businessModel' => 'businessBasic',
            ])
        );
    }

    private function createService(
        ?LoggerInterface $logger = null,
        ?RequestService $requestService = null,
        ?BusinessServicesRepository $businessServicesRepository = null,
        ?CorporateIdentityRepository $corporateIdentityRepository = null,
        ?CorporateRegistrationDatabaseService $corporateRegistrationDatabaseService = null,
        ?IdentityService $identityService = null,
        ?CorporateAuthorizedResponseFactory $corporateAuthorizedResponseFactory = null,
    ): CorporateRegistrationService {
        $params = $this->createMock(ContainerBagInterface::class);
        $params->method('get')->willReturnMap([
            ['DATA_HASH_SECRET', 'test-data-hash-secret'],
        ]);

        return new CorporateRegistrationService(
            $params,
            $corporateRegistrationDatabaseService ?? $this->createMock(CorporateRegistrationDatabaseService::class),
            $identityService ?? $this->createMock(IdentityService::class),
            $corporateIdentityRepository ?? $this->createMock(CorporateIdentityRepository::class),
            new CrypterService($params),
            $logger ?? $this->createMock(LoggerInterface::class),
            $requestService ?? $this->createMock(RequestService::class),
            $businessServicesRepository ?? $this->createMock(BusinessServicesRepository::class),
            $corporateAuthorizedResponseFactory ?? $this->createMock(CorporateAuthorizedResponseFactory::class),
        );
    }
}
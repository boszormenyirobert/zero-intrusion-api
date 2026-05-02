<?php

declare(strict_types=1);

namespace App\Tests\Service\Account;

use App\DTO\Account\AccountRequestDTO;
use App\Entity\BusinessServices;
use App\Entity\CorporateIdentity;
use App\Entity\Identity;
use App\Repository\CorporateIdentityRepository;
use App\Repository\IdentityRepository;
use App\Service\Account\AccountLookupService;
use App\Service\Crypters\CrypterDatabaseService;
use PHPUnit\Framework\TestCase;

final class AccountLookupServiceTest extends TestCase
{
    public function testHandleReturnsDecryptedCorporatesAndBusinessSubscription(): void
    {
        $request = new AccountRequestDTO('public-1', 'user@example.test');
        $businessService = new BusinessServices();
        $identity = (new Identity())->setPublicId('public-1')->setBusinessService($businessService);
        $corporate = (new CorporateIdentity())->setCorporateId('corp-1')->setIv(base64_encode(random_bytes(16)))->setCorporateIdKey('key')->setCorporateIdSecret('secret')->setSslPrivateKey('ssl');
        $decryptedCorporate = (new CorporateIdentity())->setCorporateId('corp-1')->setIv(base64_encode(random_bytes(16)))->setCorporateIdKey('key')->setCorporateIdSecret('secret')->setSslPrivateKey('ssl');

        $identityRepository = $this->createMock(IdentityRepository::class);
        $identityRepository
            ->expects(self::once())
            ->method('findOneBy')
            ->with(['publicId' => 'public-1'])
            ->willReturn($identity);

        $corporateRepository = $this->createMock(CorporateIdentityRepository::class);
        $corporateRepository
            ->expects(self::once())
            ->method('findBy')
            ->with(['businessServices' => $businessService])
            ->willReturn([$corporate]);

        $crypterDatabaseService = $this->createMock(CrypterDatabaseService::class);
        $crypterDatabaseService
            ->expects(self::once())
            ->method('decryptFromDatabase')
            ->with($corporate)
            ->willReturn($decryptedCorporate);

        $service = new AccountLookupService($corporateRepository, $identityRepository, $crypterDatabaseService);
        $response = $service->handle($request);

        self::assertSame([$decryptedCorporate], $response->accounts);
        self::assertSame($businessService, $response->businessSubscription);
    }
}

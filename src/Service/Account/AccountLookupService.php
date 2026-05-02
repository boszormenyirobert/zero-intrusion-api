<?php

declare(strict_types=1);

namespace App\Service\Account;

use App\DTO\Account\AccountRequestDTO;
use App\DTO\Account\AccountResponseDTO;
use App\Repository\CorporateIdentityRepository;
use App\Repository\IdentityRepository;
use App\Service\Crypters\CrypterDatabaseService;

class AccountLookupService
{
    public function __construct(
        private readonly CorporateIdentityRepository $corporateIdentityRepository,
        private readonly IdentityRepository $identityRepository,
        private readonly CrypterDatabaseService $crypterDatabaseService,
    ) {
    }

    public function handle(AccountRequestDTO $request): AccountResponseDTO
    {
        $userBusinessData = $this->identityRepository->findOneBy([
            'publicId' => $request->publicId,
        ]);

        if ($userBusinessData === null) {
            throw new \RuntimeException('Identity not found.');
        }

        $businessService = $userBusinessData->getBusinessService();
        $corporates = $this->corporateIdentityRepository->findBy([
            'businessServices' => $businessService,
        ]);

        $decryptedCorporates = [];
        foreach ($corporates as $corporate) {
            $decryptedCorporates[] = $this->crypterDatabaseService->decryptFromDatabase($corporate);
        }

        return new AccountResponseDTO($decryptedCorporates, $businessService);
    }
}

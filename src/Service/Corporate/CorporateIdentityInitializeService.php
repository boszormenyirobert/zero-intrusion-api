<?php

declare(strict_types=1);

namespace App\Service\Corporate;

use App\DTO\Corporate\CorporateIdentityInitializeRequestDTO;
use App\DTO\Corporate\CorporateInitializeResponseDTO;
use App\Repository\IdentityRepository;

class CorporateIdentityInitializeService
{
    public function __construct(
        private readonly CorporateRegistrationService $corporateRegistrationService,
        private readonly IdentityRepository $identityRepository,
    ) {
    }

    public function handle(CorporateIdentityInitializeRequestDTO $request): CorporateInitializeResponseDTO
    {
        $resolvedRequest = $request;

        if ($request->scope === 'external') {
            $identity = $this->identityRepository->findOneBy(['publicId' => $request->publicId]);
            $businessModel = $identity !== null
                ? $this->corporateRegistrationService->getSelectedSubscription($identity->getBusinessService())
                : null;

            $resolvedRequest = $request->withBusinessModel($businessModel);
        }

        return CorporateInitializeResponseDTO::fromServiceResult(
            $this->corporateRegistrationService->getSubscriptionData($resolvedRequest->toArray())
        );
    }
}

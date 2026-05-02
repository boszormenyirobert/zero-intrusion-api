<?php

declare(strict_types=1);

namespace App\Service\Business;

use App\DTO\Business\BusinessCreateRequestDTO;
use App\DTO\Business\BusinessCreateResponseDTO;
use App\Service\Corporate\CorporateRegistrationService;

class BusinessCreateService
{
    public function __construct(
        private readonly CorporateRegistrationService $corporateRegistrationService,
    ) {
    }

    public function handle(BusinessCreateRequestDTO $request): BusinessCreateResponseDTO
    {
        return BusinessCreateResponseDTO::fromServiceResult(
            $this->corporateRegistrationService->getBusinessRegistration($request->toArray())
        );
    }
}

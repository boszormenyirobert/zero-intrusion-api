<?php

declare(strict_types=1);

namespace App\Service\Corporate;

use App\DTO\Corporate\CorporateFollowUpRequestDTO;
use App\DTO\Corporate\CorporateFollowUpResultDTO;
use App\Exception\CorporateRegistrationException;

class CorporateFollowUpService
{
    public function __construct(
        private readonly CorporateRegistrationService $corporateRegistrationService,
    ) {
    }

    public function handle(CorporateFollowUpRequestDTO $request): CorporateFollowUpResultDTO
    {
        try {
            $this->corporateRegistrationService->updateSubscriptionDataOrFail($request->toArray());

            return CorporateFollowUpResultDTO::success();
        } catch (CorporateRegistrationException $exception) {
            return CorporateFollowUpResultDTO::error([
                'error' => true,
                'message' => $exception->getMessage(),
            ]);
        }
    }
}

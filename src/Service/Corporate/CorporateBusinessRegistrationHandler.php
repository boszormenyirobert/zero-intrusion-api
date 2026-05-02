<?php

declare(strict_types=1);

namespace App\Service\Corporate;

final class CorporateBusinessRegistrationHandler
{
    public function __construct(
        private readonly CorporateRegistrationDatabaseService $corporateRegistrationDatabaseService,
        private readonly CorporateAuthorizedResponseFactory $corporateAuthorizedResponseFactory,
    ) {
    }

    /** @param array{businessModel: string, publicId: string} $data */
    public function handle(array $data): array
    {
        $businessSubscription = $this->corporateRegistrationDatabaseService->generateBusinessService($data['businessModel']);
        $this->corporateRegistrationDatabaseService->updateUserIdentity($data['publicId'], $businessSubscription);

        return $this->corporateAuthorizedResponseFactory->create((array) $businessSubscription);
    }
}
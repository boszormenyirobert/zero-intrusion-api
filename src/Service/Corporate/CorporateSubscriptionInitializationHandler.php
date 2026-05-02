<?php

declare(strict_types=1);

namespace App\Service\Corporate;

final class CorporateSubscriptionInitializationHandler
{
    public function __construct(
        private readonly IdentityService $identityService,
        private readonly CorporateRegistrationDatabaseService $corporateRegistrationDatabaseService,
        private readonly CorporateAuthorizedResponseFactory $corporateAuthorizedResponseFactory,
    ) {
    }

    /** @param array{publicId: string, scope: string, businessModel: ?string} $data */
    public function handle(array $data): array
    {
        $this->identityService->initializeIdentity($data['businessModel'], $data['publicId'], $data['scope']);
        $identity = $this->identityService->getIdentity();

        $this->corporateRegistrationDatabaseService->createUserCorporateRelation($data['publicId'], $identity['corporate_id']);

        return $this->corporateAuthorizedResponseFactory->create($identity);
    }
}
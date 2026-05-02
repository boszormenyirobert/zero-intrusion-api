<?php

declare(strict_types=1);

namespace App\Service\Corporate;

use App\Entity\CorporateIdentity;
use App\Exception\CorporateRegistrationException;
use App\Repository\CorporateIdentityRepository;

final class CorporateFollowUpUpdater
{
    public function __construct(
        private readonly CorporateIdentityRepository $corporateIdentityRepository,
        private readonly CorporateRegistrationDatabaseService $corporateRegistrationDatabaseService,
    ) {
    }

    public function handle(array $corporateFollowUpData): CorporateIdentity
    {
        try {
            if (
                !isset($corporateFollowUpData['updateIdentity']['corporateId'])
                || empty($corporateFollowUpData['updateIdentity']['corporateId'])
            ) {
                throw new CorporateRegistrationException('CorporateId missing in the follow-up data');
            }

            $corporate = $this->corporateIdentityRepository->findOneBy([
                'corporateId' => $corporateFollowUpData['updateIdentity']['corporateId'],
            ]);

            if (!$corporate instanceof CorporateIdentity) {
                throw new CorporateRegistrationException('CorporateId is not registrated in the database');
            }

            return $this->corporateRegistrationDatabaseService->addFollowUpData($corporate, $corporateFollowUpData);
        } catch (CorporateRegistrationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new CorporateRegistrationException($e->getMessage(), previous: $e);
        }
    }
}
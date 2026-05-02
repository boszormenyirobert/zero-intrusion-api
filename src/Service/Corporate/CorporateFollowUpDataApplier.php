<?php

declare(strict_types=1);

namespace App\Service\Corporate;

use App\Entity\CorporateIdentity;

final class CorporateFollowUpDataApplier
{
    /** @param array{updateIdentity: array{callbackUserLogin: string, callbackUserRegistration: string, domain: string}} $followUpDecryptedCorporateData */
    public function apply(CorporateIdentity $corporateIdentity, array $followUpDecryptedCorporateData): void
    {
        $corporateIdentity->setCallbackUserLogin($followUpDecryptedCorporateData['updateIdentity']['callbackUserLogin']);
        $corporateIdentity->setCallbackUserRegistration($followUpDecryptedCorporateData['updateIdentity']['callbackUserRegistration']);
        $corporateIdentity->setDomain($followUpDecryptedCorporateData['updateIdentity']['domain']);
    }
}
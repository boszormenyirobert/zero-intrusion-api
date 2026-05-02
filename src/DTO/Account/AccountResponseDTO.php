<?php

declare(strict_types=1);

namespace App\DTO\Account;

use App\Entity\BusinessServices;
use App\Entity\CorporateIdentity;

final readonly class AccountResponseDTO
{
    public function __construct(
        public array $accounts,
        public mixed $businessSubscription,
    ) {
    }

    public function toArray(): array
    {
        return [
            'accounts' => array_map($this->normalizeAccount(...), $this->accounts),
            'businessSubscription' => $this->normalizeBusinessSubscription($this->businessSubscription),
        ];
    }

    private function normalizeAccount(mixed $account): array
    {
        if (is_array($account)) {
            return $account;
        }

        if ($account instanceof CorporateIdentity) {
            return [
                'domain' => $account->getDomain(),
                'callbackUserLogin' => $account->getCallbackUserLogin(),
                'callbackUserRegistration' => $account->getCallbackUserRegistration(),
                'corporateIdKey' => $account->getCorporateIdKey(),
                'corporateIdSecret' => $account->getCorporateIdSecret(),
                'iv' => $account->getIv(),
                'corporateId' => $account->getCorporateId(),
                'sslPublicKey' => $account->getSslPublicKey(),
            ];
        }

        return (array) $account;
    }

    private function normalizeBusinessSubscription(mixed $businessSubscription): array
    {
        if (is_array($businessSubscription)) {
            return $businessSubscription;
        }

        if ($businessSubscription instanceof BusinessServices) {
            $passwordManager = $businessSubscription->isPasswordManager();

            return [
                'id' => $businessSubscription->getId(),
                'pswManager' => $passwordManager,
                'passwordManager' => $passwordManager,
                'biometric' => $businessSubscription->isBiometric(),
                'basic' => $businessSubscription->isBasic(),
                'plus' => $businessSubscription->isPlus(),
                'pro' => $businessSubscription->isPro(),
            ];
        }

        return (array) $businessSubscription;
    }
}

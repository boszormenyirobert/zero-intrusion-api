<?php

declare(strict_types=1);

namespace App\Service\AccessRegistry\CredentialHubResolver;

use App\Entity\AccessRegistry;

final class CheckService
{
    public function userDomainCombinationExists(array $user, array $decryptedUserPages, string $key): array
    {
        $newCombination = true;
        $existingPages = [];

        foreach ($decryptedUserPages as $registratedPage) {
            // Allow multiple credentials for the same domain 
            if ($key === 'domain' && $registratedPage->getPublicId() === $user['publicId'] && $registratedPage->getDomain() === $user['domain']) {
                // Do not break
                //$newCombination = false;
                //break;
            } else {
                if ($key === 'application' && $registratedPage->getPublicId() === $user['publicId'] 
                && array_key_exists('application', $user)
                && $registratedPage->getApplication() === $user['application']) {
                    $newCombination = false;
                    break;
                }
            }
        }

        return [
            "newCombination" => $newCombination,
            "existingPage" => $existingPages
        ];
    }

    public function getUserDomainCombination(array $user, array $decryptedUserPages): ?AccessRegistry
    {
        $decryptedPage = null;

        foreach ($decryptedUserPages as $registratedPage) {

            if ($registratedPage->getPublicId() === $user['publicId'] && 
                $registratedPage->getDomain() === $user['domain']&& 
                $registratedPage->getTargetId() === $user['targetId']
            ) {
                $decryptedPage = $registratedPage;
                break;
            }
        }

        return $decryptedPage;
    }
}
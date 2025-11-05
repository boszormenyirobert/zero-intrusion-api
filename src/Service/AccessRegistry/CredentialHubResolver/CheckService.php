<?php

namespace App\Service\AccessRegistry\CredentialHubResolver;


final class CheckService
{
    public function userDomainCombinationExists(array $user, $decryptedUserPages, $key)
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
                if ($key === 'application' && $registratedPage->getPublicId() === $user['publicId'] && $registratedPage->getApplication() === $user['application']) {
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

    public function getUserDomainCombination(array $user, $decryptedUserPages)
    {
        $decryptedPage = null;

        foreach ($decryptedUserPages as $registratedPage) {

            if ($registratedPage->getPublicId() === $user['publicId'] && $registratedPage->getDomain() === $user['domain']) {
                $decryptedPage = $registratedPage;
                break;
            }
        }

        return $decryptedPage;
    }
}
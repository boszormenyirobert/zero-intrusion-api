<?php

namespace App\Controller\CredentialHub\Vault\Read;

use App\DTO\QR\VaultReadQrContentDTO;

class VaultReadService
{
    public function getQrContent($type, $source, $mobilXExtensionAuth, $identity): VaultReadQrContentDTO
    {
        return new VaultReadQrContentDTO(
            $identity->getApplicationProcessId(),
            $type,
            $source,
            $mobilXExtensionAuth,
            $identity->getIv()
        );
    }

    public function getDecryptedCredentials(array $user): array{
        $applicationList = [];
        $getPages = $this->accessRegistryRepository->findBy(['publicId' => $publicId]);
        foreach ($getPages as $userPage) {
            if ($userPage->getApplication() !== null) {
                $decrypted = $this->crypterDatabaseUserService->decryptFromDatabase($userPage, "application");
                $applicationList[] = $decrypted;
            }
        }
        return $applicationList;
    }
}
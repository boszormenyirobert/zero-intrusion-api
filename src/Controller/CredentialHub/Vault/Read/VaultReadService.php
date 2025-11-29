<?php

namespace App\Controller\CredentialHub\Vault\Read;

use App\DTO\QR\VaultReadQrContentDTO;
use App\Repository\AccessRegistryRepository;
use App\Service\Crypters\CrypterDatabaseUserService;

class VaultReadService
{
    public function __construct(
        private AccessRegistryRepository $accessRegistryRepository,
        private CrypterDatabaseUserService $crypterDatabaseUserService
    ) {}

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

    public function getDecryptedCredentials(string $publicId): array{
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
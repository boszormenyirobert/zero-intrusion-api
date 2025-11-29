<?php

namespace App\Controller\CredentialHub\Vault\Read;

use App\DTO\QR\VaultReadQrContentDTO;
use App\Repository\AccessRegistryRepository;
use App\Service\AccessRegistry\Database\CrypterDatabaseAccessRegistryService;


class VaultReadService
{
    public function __construct(
        private AccessRegistryRepository $accessRegistryRepository,
        private CrypterDatabaseAccessRegistryService $crypterDatabaseAccessRegistryService
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
        return $getPages;
        foreach ($getPages as $userPage) {
            if ($userPage->getApplication() !== null) {
                $decrypted = $this->crypterDatabaseAccessRegistryService->decryptFromDatabase($userPage, "application");
                $applicationList[] = $decrypted;
            }
        }
        return $applicationList;
    }
}
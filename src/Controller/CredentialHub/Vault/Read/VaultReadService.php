<?php

namespace App\Controller\CredentialHub\Vault\Read;

use App\DTO\QR\VaultReadQrContentDTO;
use App\Repository\AccessRegistryRepository;
use App\Service\AccessRegistry\Database\CrypterDatabaseAccessRegistryService;
use Psr\Log\LoggerInterface;

class VaultReadService
{
    public function __construct(
        private AccessRegistryRepository $accessRegistryRepository,
        private CrypterDatabaseAccessRegistryService $crypterDatabaseAccessRegistryService,
        private LoggerInterface $logger
    ) {}

    public function getQrContent($identity): VaultReadQrContentDTO
    {
        return new VaultReadQrContentDTO(
            $identity
        );
    }

    public function getDecryptedCredentials(string $publicId): array{
        $applicationList = [];
        $getPages = $this->accessRegistryRepository->findBy(['publicId' => $publicId]);
        foreach ($getPages as $userPage) {
            if ($userPage->getApplication() !== null) {
                $decrypted = $this->crypterDatabaseAccessRegistryService->decryptFromDatabaseOrFail($userPage, "application");

                $applicationList[] = [
                    'credential' => $decrypted->getUserCredential(), 
                    'description' => $decrypted->getDescription(),
                    'targetId' => $decrypted->getTargetId(),
                    'application' => $decrypted->getApplication()  
                ];
            }
        }
        return $applicationList;
    }
}
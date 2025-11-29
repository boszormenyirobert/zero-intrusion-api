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
        $this->logger->critical('Encrypting application data for database storage.' . json_encode($publicId));
        $this->logger->critical('Encrypting application data for database storage.' . json_encode(\count($getPages)));

        foreach ($getPages as $userPage) {
            if ($userPage->getApplication() !== null) {
                $decrypted = $this->crypterDatabaseAccessRegistryService->decryptFromDatabase($userPage, "application");
                $applicationList[] = $decrypted;
            }
        }
        return $applicationList;
    }
}
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
        foreach ($getPages as $userPage) {
            if ($userPage->getApplication() !== null) {
                $this->logger->critical('Encrypting application data for database storage application !!! :' . json_encode(($userPage->getApplication())));
                $this->logger->critical('Encrypting application data for database storage application !!! :' . json_encode(($userPage->getPublicId())));
                $this->logger->critical('Encrypting application data for database storage application !!! :' . json_encode(($userPage->getTargetId())));
                $this->logger->critical('Encrypting application data for database storage application !!! :' . json_encode(($userPage->getUserCredential())));

                $decrypted = $this->crypterDatabaseAccessRegistryService->decryptFromDatabase($userPage, "application");
                $this->logger->critical('Encrypting application data for database storage application !!! :' . json_encode(($decrypted->getUserCredential())));

                $applicationList[] = $decrypted;
            }
        }
        return $applicationList;
    }
}
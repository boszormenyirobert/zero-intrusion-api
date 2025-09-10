<?php

namespace App\Service\AuthBridge\AuthBridgeHandler\Application;

use App\Repository\AccessRegistryRepository;
use App\Service\Crypters\SodiumService;
use App\Service\AccessRegistry\Database\CrypterDatabaseAccessRegistryService;

class ListBuilder
{
    public function __construct(
        private AccessRegistryRepository $accessRegistryRepository,
        private CrypterDatabaseAccessRegistryService $crypterDatabaseUserService,
        private SodiumService $sodiumService
    ) {}

    public function buildDecryptedApplicationList(string $publicId, string $userSecret): array
    {
        $applicationList = [];
        $getPages = $this->accessRegistryRepository->findBy(['publicId' => $publicId]);

        foreach ($getPages as $userPage) {
            if ($userPage->getApplication() !== null) {
                $decrypted = $this->crypterDatabaseUserService->decryptFromDatabase($userPage, "application");
                $decrypted->setUserCredential(
                    $this->sodiumService->sodiumDecrypt($decrypted->getUserCredential(), $userSecret)
                );
                $applicationList[] = $decrypted;
            }
        }

        return $applicationList;
    }
}

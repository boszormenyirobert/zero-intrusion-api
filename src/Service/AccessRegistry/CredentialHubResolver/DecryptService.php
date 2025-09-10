<?php

namespace App\Service\AccessRegistry\CredentialHubResolver;

use App\Service\AccessRegistry\Database\CrypterDatabaseAccessRegistryService;

final class DecryptService
{
    public function __construct(
        private CrypterDatabaseAccessRegistryService $crypterDatabaseAccessRegistryService,
    ) {}

    public function getUserDecryptedPages($userPages, $key)
    {
        $decrypted = [];
        foreach ($userPages as $user) {
            array_push($decrypted, $this->crypterDatabaseAccessRegistryService->decryptFromDatabase($user, $key));
        }

        return $decrypted;
    }

    public function getUserEncryptedDecryptedPageCollection($userPages)
    {
        $collecion = [];

        foreach ($userPages as $user) {
            $page = [
                'encrypted' => $user,
                'decrypted' => $this->crypterDatabaseAccessRegistryService->decryptFromDatabase($user)
            ];
            array_push($collecion, $page);
        }

        return $collecion;
    }
}
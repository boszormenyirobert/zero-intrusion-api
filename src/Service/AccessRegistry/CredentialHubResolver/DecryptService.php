<?php

declare(strict_types=1);

namespace App\Service\AccessRegistry\CredentialHubResolver;

use App\Service\AccessRegistry\Database\CrypterDatabaseAccessRegistryService;

final class DecryptService
{
    public function __construct(
        private readonly CrypterDatabaseAccessRegistryService $crypterDatabaseAccessRegistryService,
    ) {}

    public function getUserDecryptedPages(array $userPages, string $key): array
    {
        $decrypted = [];

        foreach ($userPages as $user) {
            $decrypted[] = $this->crypterDatabaseAccessRegistryService->decryptFromDatabaseOrFail($user, $key);
        }

        return $decrypted;
    }

    public function getUserEncryptedDecryptedPageCollection(array $userPages): array
    {
        $collecion = [];

        foreach ($userPages as $user) {
            $collecion[] = [
                'encrypted' => $user,
                'decrypted' => $this->crypterDatabaseAccessRegistryService->decryptFromDatabaseOrFail($user)
            ];
        }

        return $collecion;
    }
}
<?php

declare(strict_types=1);

namespace App\DTO\CredentialHub\Vault\Read;

final readonly class VaultReadCredentialResultDTO
{
    public function __construct(
        public bool $applicationAccessProcess,
        public string $error,
    ) {
    }

    /**
     * @return array{application_access_process: bool, error: string}
     */
    public function toArray(): array
    {
        return [
            'application_access_process' => $this->applicationAccessProcess,
            'error' => $this->error,
        ];
    }
}
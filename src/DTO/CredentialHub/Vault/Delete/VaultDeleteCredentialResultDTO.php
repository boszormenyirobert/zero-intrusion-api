<?php

declare(strict_types=1);

namespace App\DTO\CredentialHub\Vault\Delete;

final readonly class VaultDeleteCredentialResultDTO
{
    public function __construct(
        public bool $deleteProcess,
        public ?string $error,
    ) {
    }

    /**
     * @return array{delete_process: bool, error: string|null}
     */
    public function toArray(): array
    {
        return [
            'delete_process' => $this->deleteProcess,
            'error' => $this->error,
        ];
    }
}
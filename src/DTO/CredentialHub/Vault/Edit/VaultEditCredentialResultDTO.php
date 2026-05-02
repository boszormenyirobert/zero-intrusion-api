<?php

declare(strict_types=1);

namespace App\DTO\CredentialHub\Vault\Edit;

final readonly class VaultEditCredentialResultDTO
{
    public function __construct(
        public ?bool $deleteProcess,
        public string $error,
    ) {
    }

    /**
     * @return array{delete_process: bool|null, error: string}
     */
    public function toArray(): array
    {
        return [
            'delete_process' => $this->deleteProcess,
            'error' => $this->error,
        ];
    }
}
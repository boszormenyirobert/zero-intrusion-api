<?php

declare(strict_types=1);

namespace App\DTO\CredentialHub\Domain\Delete;

final readonly class DomainDeleteCredentialResultDTO
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
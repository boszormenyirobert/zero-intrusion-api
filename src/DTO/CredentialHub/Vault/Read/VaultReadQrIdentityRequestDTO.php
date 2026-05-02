<?php

declare(strict_types=1);

namespace App\DTO\CredentialHub\Vault\Read;

final readonly class VaultReadQrIdentityRequestDTO
{
    public function __construct(
        public ?string $source,
        public ?string $type,
        public ?string $userPublicId,
        public array $payload,
    ) {
    }

    public static function fromArray(array $payload): self
    {
        return new self(
            $payload['source'] ?? null,
            $payload['type'] ?? null,
            $payload['userPublicId'] ?? null,
            $payload,
        );
    }
}
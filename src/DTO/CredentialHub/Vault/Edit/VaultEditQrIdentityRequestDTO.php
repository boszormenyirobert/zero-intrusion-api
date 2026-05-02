<?php

declare(strict_types=1);

namespace App\DTO\CredentialHub\Vault\Edit;

final readonly class VaultEditQrIdentityRequestDTO
{
    public function __construct(
        public ?string $userPublicId,
        public array $payload,
    ) {
    }

    public static function fromArray(array $payload): self
    {
        return new self(
            $payload['userPublicId'] ?? null,
            $payload,
        );
    }

    public function toObject(): object
    {
        return (object) $this->payload;
    }
}
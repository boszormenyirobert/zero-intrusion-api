<?php

declare(strict_types=1);

namespace App\DTO\CredentialHub;

final readonly class ExtensionCredentialRequestDTO
{
    public function __construct(
        public ?string $domain,
        public ?string $userPublicId,
        public ?string $publicKey,
    ) {
    }

    public static function fromArray(array $payload): self
    {
        return new self(
            $payload['domain'] ?? null,
            $payload['userPublicId'] ?? null,
            $payload['publicKey'] ?? null,
        );
    }
}
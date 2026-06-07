<?php

declare(strict_types=1);

namespace App\DTO\CredentialHub;

final readonly class ExtensionCredentialRequestDTO
{
    public function __construct(
        public ?string $domain,
        public ?string $userPublicId,
        public ?string $publicKey,
        public ?string $isNew = 'new',
    ) {
    }

    public static function fromArray(array $payload): self
    {
        return new self(
            $payload['domain'] ?? null,
            $payload['userPublicId'] ?? null,
            $payload['publicKey'] ?? null,
            array_key_exists('isNew', $payload) ? (string) $payload['isNew'] : null,
        );
    }
}
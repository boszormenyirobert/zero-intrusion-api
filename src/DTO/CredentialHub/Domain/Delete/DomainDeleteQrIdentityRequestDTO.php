<?php

declare(strict_types=1);

namespace App\DTO\CredentialHub\Domain\Delete;

final readonly class DomainDeleteQrIdentityRequestDTO
{
    public function __construct(
        public ?string $domain,
        public ?string $type,
        public ?string $source,
        public ?string $targetId,
        public ?string $userPublicId,
    ) {
    }

    public static function fromArray(array $payload): self
    {
        return new self(
            $payload['domain'] ?? null,
            $payload['type'] ?? null,
            $payload['source'] ?? null,
            $payload['targetId'] ?? null,
            $payload['userPublicId'] ?? null,
        );
    }
}
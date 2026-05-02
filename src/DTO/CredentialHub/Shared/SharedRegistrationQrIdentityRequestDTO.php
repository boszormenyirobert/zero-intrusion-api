<?php

declare(strict_types=1);

namespace App\DTO\CredentialHub\Shared;

final readonly class SharedRegistrationQrIdentityRequestDTO
{
    public function __construct(
        public ?string $type,
        public ?string $userPublicId,
        public array $payload,
    ) {
    }

    public static function fromArray(array $payload): self
    {
        return new self(
            $payload['type'] ?? null,
            $payload['userPublicId'] ?? null,
            $payload,
        );
    }

    public function toObject(): object
    {
        return (object) $this->payload;
    }
}
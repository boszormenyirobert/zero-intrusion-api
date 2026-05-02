<?php

declare(strict_types=1);

namespace App\DTO\CredentialHub\OneTouch;

final readonly class OneTouchQrIdentityRequestDTO
{
    public function __construct(
        public ?string $type,
        public array $payload,
    ) {
    }

    public static function fromArray(array $payload): self
    {
        return new self(
            $payload['type'] ?? null,
            $payload,
        );
    }

    public function toObject(): object
    {
        return (object) $this->payload;
    }
}

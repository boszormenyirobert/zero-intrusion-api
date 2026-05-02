<?php

declare(strict_types=1);

namespace App\DTO\Account;

final readonly class AccountRequestDTO
{
    public function __construct(
        public ?string $publicId,
        public ?string $email,
    ) {
    }

    public static function fromArray(array $payload): self
    {
        return new self(
            $payload['publicId'] ?? null,
            $payload['email'] ?? null,
        );
    }
}

<?php

declare(strict_types=1);

namespace App\DTO\Device\Restore;

final readonly class ReplaceDeviceRequestDTO
{
    public function __construct(
        public ?string $email,
        public ?string $phone,
    ) {
    }

    public static function fromArray(array $payload): self
    {
        return new self(
            $payload['email'] ?? null,
            $payload['phone'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'email' => $this->email,
            'phone' => $this->phone,
        ];
    }
}

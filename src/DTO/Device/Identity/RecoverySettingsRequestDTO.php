<?php

declare(strict_types=1);

namespace App\DTO\Device\Identity;

final readonly class RecoverySettingsRequestDTO
{
    public function __construct(
        public ?string $publicId,
        public ?string $privateId,
        public ?string $email,
        public ?string $phone,
        public mixed $privacyPolicy,
        public mixed $fcmToken,
    ) {
    }

    public static function fromArray(array $payload): self
    {
        return new self(
            $payload['publicId'] ?? null,
            $payload['privateId'] ?? null,
            $payload['email'] ?? null,
            $payload['phone'] ?? null,
            $payload['privacyPolicy'] ?? null,
            $payload['fcmToken'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'publicId' => $this->publicId,
            'privateId' => $this->privateId,
            'email' => $this->email,
            'phone' => $this->phone,
            'privacyPolicy' => $this->privacyPolicy,
            'fcmToken' => $this->fcmToken,
        ];
    }
}

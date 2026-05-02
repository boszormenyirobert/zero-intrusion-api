<?php

declare(strict_types=1);

namespace App\DTO\User\Login;

final readonly class LoginQrIdentityRequestDTO
{
    public function __construct(
        public ?string $corporatePublicId,
        public array|string|null $corporateAuthentication,
        public ?string $domain,
        public ?string $userPublicId = null,
    ) {
    }

    public static function fromArray(array $payload): self
    {
        return new self(
            $payload['corporatePublicId'] ?? null,
            $payload['corporateAuthentication'] ?? null,
            $payload['domain'] ?? null,
            $payload['userPublicId'] ?? null,
        );
    }

    public function hasUserPublicId(): bool
    {
        return is_string($this->userPublicId) && $this->userPublicId !== '';
    }

    public function toPayload(): array
    {
        return [
            'corporatePublicId' => $this->corporatePublicId,
            'corporateAuthentication' => $this->corporateAuthentication,
            'domain' => $this->domain,
            'userPublicId' => $this->userPublicId,
        ];
    }
}

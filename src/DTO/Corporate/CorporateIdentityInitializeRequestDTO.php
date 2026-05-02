<?php

declare(strict_types=1);

namespace App\DTO\Corporate;

final readonly class CorporateIdentityInitializeRequestDTO
{
    public function __construct(
        public ?string $publicId,
        public ?string $scope,
        public ?string $businessModel,
    ) {
    }

    public static function fromArray(array $payload): self
    {
        return new self(
            $payload['publicId'] ?? null,
            $payload['scope'] ?? null,
            $payload['businessModel'] ?? null,
        );
    }

    public function withBusinessModel(?string $businessModel): self
    {
        return new self($this->publicId, $this->scope, $businessModel);
    }

    public function toArray(): array
    {
        return [
            'publicId' => $this->publicId,
            'scope' => $this->scope,
            'businessModel' => $this->businessModel,
        ];
    }
}

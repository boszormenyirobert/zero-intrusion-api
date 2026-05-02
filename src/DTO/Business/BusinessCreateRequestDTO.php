<?php

declare(strict_types=1);

namespace App\DTO\Business;

final readonly class BusinessCreateRequestDTO
{
    public function __construct(
        public ?string $businessModel,
        public ?string $publicId,
        public ?string $scope,
    ) {
    }

    public static function fromArray(array $payload): self
    {
        return new self(
            $payload['businessModel'] ?? null,
            $payload['publicId'] ?? null,
            $payload['scope'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'businessModel' => $this->businessModel,
            'publicId' => $this->publicId,
            'scope' => $this->scope,
        ];
    }
}

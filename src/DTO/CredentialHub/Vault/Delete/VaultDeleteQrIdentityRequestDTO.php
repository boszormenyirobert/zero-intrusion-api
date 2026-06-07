<?php

declare(strict_types=1);

namespace App\DTO\CredentialHub\Vault\Delete;

final readonly class VaultDeleteQrIdentityRequestDTO
{
    public function __construct(
        public ?string $type,
        public ?string $source,
        public ?string $targetId,
        public ?string $userPublicId,
    ) {
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function getTargetId(): ?string
    {
        return $this->targetId;
    }

    public function getUserPublicId(): ?string
    {
        return $this->userPublicId;
    }

    public static function fromArray(array $payload): self
    {
        return new self(
            $payload['type'] ?? null,
            $payload['source'] ?? null,
            $payload['targetId'] ?? null,
            $payload['userPublicId'] ?? null,
        );
    }
}
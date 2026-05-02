<?php

declare(strict_types=1);

namespace App\DTO\Device\Nfc;

final readonly class NfcDecryptRequestDTO
{
    public function __construct(
        public ?string $userPublicId,
        public ?string $corporatePublicId,
        public ?string $nfcData,
    ) {
    }

    public static function fromArray(array $payload): self
    {
        return new self(
            $payload['userPublicId'] ?? null,
            $payload['publicId'] ?? null,
            $payload['nfcData'] ?? null,
        );
    }
}

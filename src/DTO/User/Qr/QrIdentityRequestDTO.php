<?php

declare(strict_types=1);

namespace App\DTO\User\Qr;

final readonly class QrIdentityRequestDTO
{
    public function __construct(
        public array $payload,
        public string $processKey,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace App\DTO\Device\Restore;

final readonly class ReplaceDevicePinRequestDTO
{
    public function __construct(
        public array $payload,
    ) {
    }

    public function toArray(): array
    {
        return $this->payload;
    }
}

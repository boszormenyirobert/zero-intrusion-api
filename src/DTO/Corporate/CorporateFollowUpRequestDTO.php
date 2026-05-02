<?php

declare(strict_types=1);

namespace App\DTO\Corporate;

final readonly class CorporateFollowUpRequestDTO
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public array $payload,
    ) {
    }

    public function toArray(): array
    {
        return $this->payload;
    }
}

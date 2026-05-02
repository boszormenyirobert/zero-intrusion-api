<?php

declare(strict_types=1);

namespace App\DTO\Hmac;

final readonly class ListenerRoutePayloadResolutionDTO
{
    /**
     * @param array<string, mixed>|null $payload
     */
    public function __construct(
        public ?array $payload,
        public bool $invalidInnerPayload,
        public bool $missingPayloadKey,
    ) {
    }
}
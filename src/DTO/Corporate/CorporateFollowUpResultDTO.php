<?php

declare(strict_types=1);

namespace App\DTO\Corporate;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

final readonly class CorporateFollowUpResultDTO
{
    /**
     * @param array<string, mixed>|null $errorPayload
     */
    private function __construct(
        public bool $successful,
        public ?array $errorPayload = null,
    ) {
    }

    public static function success(): self
    {
        return new self(true);
    }

    /**
     * @param array<string, mixed> $errorPayload
     */
    public static function error(array $errorPayload): self
    {
        return new self(false, $errorPayload);
    }

    public function toResponse(): Response
    {
        if ($this->errorPayload !== null) {
            return new JsonResponse($this->errorPayload);
        }

        return new Response('1');
    }
}

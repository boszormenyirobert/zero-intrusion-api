<?php

declare(strict_types=1);

namespace App\DTO\Corporate;

use Symfony\Component\HttpFoundation\Response;

final readonly class CorporateInitializeResponseDTO
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        public string $body,
        public array $headers,
    ) {
    }

    public static function fromServiceResult(array $response): self
    {
        return new self(
            (string) ($response['body'] ?? ''),
            is_array($response['headers'] ?? null) ? $response['headers'] : [],
        );
    }

    public function toResponse(): Response
    {
        return new Response($this->body, Response::HTTP_OK, $this->headers);
    }
}

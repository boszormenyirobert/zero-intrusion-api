<?php

declare(strict_types=1);

namespace App\DTO\User\Qr;

use Symfony\Component\HttpFoundation\Response;

final readonly class QrIdentityResultDTO
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        public string $body,
        public array $headers,
    ) {
    }

    public static function fromServiceResult(array $qrData): self
    {
        $defaultResponse = $qrData['defaultResponse'] ?? [];

        return new self(
            (string) ($defaultResponse['body'] ?? ''),
            is_array($defaultResponse['headers'] ?? null) ? $defaultResponse['headers'] : [],
        );
    }

    public function toResponse(): Response
    {
        return new Response($this->body, Response::HTTP_OK, $this->headers);
    }
}

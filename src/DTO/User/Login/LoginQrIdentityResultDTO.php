<?php

declare(strict_types=1);

namespace App\DTO\User\Login;

use Symfony\Component\HttpFoundation\Response;

final readonly class LoginQrIdentityResultDTO
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(
        public string $body,
        public array $headers,
        public mixed $mobileResponse,
    ) {
    }

    public static function fromServiceResult(array $qrData): self
    {
        $defaultResponse = $qrData['defaultResponse'] ?? [];

        return new self(
            (string) ($defaultResponse['body'] ?? ''),
            is_array($defaultResponse['headers'] ?? null) ? $defaultResponse['headers'] : [],
            $qrData['mobileResponse'] ?? null,
        );
    }

    public function toResponse(): Response
    {
        return new Response($this->body, Response::HTTP_OK, $this->headers);
    }
}

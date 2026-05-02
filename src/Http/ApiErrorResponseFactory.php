<?php

declare(strict_types=1);

namespace App\Http;

use Symfony\Component\HttpFoundation\JsonResponse;

final class ApiErrorResponseFactory
{
    public function create(string $message, int $statusCode): JsonResponse
    {
        return new JsonResponse($this->createPayload($message), $statusCode);
    }

    /**
     * @return array{success: false, error: string}
     */
    public function createPayload(string $message): array
    {
        return [
            'success' => false,
            'error' => $message,
        ];
    }
}
<?php

declare(strict_types=1);

namespace App\Service\Payload;

use App\Service\Shared\RequestService;
use Symfony\Component\HttpFoundation\Request;

final class ValidatedPayloadResolver
{
    public function __construct(
        private readonly RequestService $requestService
    ) {
    }

    public function resolve(Request $request): array
    {
        /** @var array<string, mixed> $payload */
        $payload = $request->attributes->get('json_payload');

        return $this->requestService->validPayload($payload);
    }
}
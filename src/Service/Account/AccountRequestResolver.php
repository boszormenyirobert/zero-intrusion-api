<?php

declare(strict_types=1);

namespace App\Service\Account;

use App\Service\Shared\RequestService;
use Symfony\Component\HttpFoundation\Request;

class AccountRequestResolver
{
    public function __construct(
        private readonly RequestService $requestService,
    ) {
    }

    public function resolve(Request $request): array
    {
        $payload = $this->requestService->validateRequest($request);

        if (is_array($payload) && array_key_exists('error', $payload) && $payload['error'] !== false) {
            throw new \InvalidArgumentException('Invalid account payload.');
        }

        return $this->requestService->validPayload($payload);
    }
}

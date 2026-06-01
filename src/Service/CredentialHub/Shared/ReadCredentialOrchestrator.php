<?php

declare(strict_types=1);

namespace App\Service\CredentialHub\Shared;

use App\Service\CredentialHub\SharedPayloadService;
use Symfony\Component\HttpFoundation\Request;

class ReadCredentialOrchestrator
{
    public function __construct(
        private readonly SharedPayloadService $sharedPayloadService,
    ) {
    }

    public function handle(
        Request $request,
        string $payloadKey,
        ReadCredentialStrategyInterface $strategy,
    ): bool {
        $payload = $this->sharedPayloadService->getPayload($request, $payloadKey);

        return $strategy->handle($payload);
    }
}

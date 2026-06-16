<?php

declare(strict_types=1);

namespace App\Service\CredentialHub\OneTouch;

use App\Controller\CredentialHub\PayloadKeys;
use App\DTO\CredentialHub\OneTouch\OneTouchIdentifierResultDTO;
use App\Service\AuthBridge\AuthBridgeService;
use App\Service\CredentialHub\SharedPayloadService;
use Symfony\Component\HttpFoundation\Request;

class OneTouchIdentifierService
{
    public function __construct(
        private readonly SharedPayloadService $sharedPayloadService,
        private readonly AuthBridgeService $authBridgeService,
    ) {
    }

    public function handle(Request $request): OneTouchIdentifierResultDTO
    {
        $payload = $this->sharedPayloadService->getPayloadOrFail($request, PayloadKeys::ONE_TOUCH_IDENTIFIER);

        if (!isset($payload['sessionId']) || !is_string($payload['sessionId']) || $payload['sessionId'] === '') {
            throw new \InvalidArgumentException('Invalid or missing sessionId from OneTouchIdentifierService.');
        }

        return new OneTouchIdentifierResultDTO(
            $this->authBridgeService->persistDecryptedUserData($payload),
            '',
        );
    }
}

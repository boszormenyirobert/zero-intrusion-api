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
        $process = $this->sharedPayloadService->getProcessId($request, PayloadKeys::ONE_TOUCH_IDENTIFIER, true);

        if (!$process) {
            throw new \InvalidArgumentException('Invalid or missing processId');
        }

        return new OneTouchIdentifierResultDTO(
            $this->authBridgeService->persistDecryptedUserData($process),
            '',
        );
    }
}

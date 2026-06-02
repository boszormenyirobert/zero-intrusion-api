<?php

declare(strict_types=1);

namespace App\Service\CredentialHub\OneTouch;

use App\Service\CredentialHub\Shared\SharedRegistrationService;
use App\DTO\CredentialHub\OneTouch\OneTouchQrIdentityRequestDTO;
use App\Service\AuthBridge\AuthBridgeService;
use App\Service\CredentialHub\Shared\QrContentValidationService;
use App\Service\QrService\QrService;

class OneTouchQrIdentityService
{
    public function __construct(
        private readonly SharedRegistrationService $sharedRegistrationService,
        private readonly AuthBridgeService $authBridgeService,
        private readonly QrService $qrService,
        private readonly QrContentValidationService $qrContentValidationService,
    ) {
    }

    public function handle(OneTouchQrIdentityRequestDTO $request): array
    {
        if ($request->type === null || $request->type === '') {
            throw new \InvalidArgumentException('Missing one-touch type');
        }

        $identity = $this->authBridgeService->generateRequestIdentity('one-touch');
        $authToken = $identity->getXExtensionAuthOne();
        $sessionId = $identity->getSessionId();
        $qrContent = $this->sharedRegistrationService->getOneTouchQrContent($request->toObject(), $authToken, $sessionId);

        $this->qrContentValidationService->validateOrFail($qrContent, 'one-touch');

        $identity->setQrCode($this->qrService->getQrCode($qrContent));

        return $identity->toOneTouchProcessArray();
    }
}

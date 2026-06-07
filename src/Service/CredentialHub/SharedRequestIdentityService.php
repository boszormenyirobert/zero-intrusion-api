<?php

declare(strict_types=1);

namespace App\Service\CredentialHub;

use App\Controller\CredentialHub\PayloadKeys;
use App\DTO\QR\VaultDeleteQrContentDTO;
use App\Service\AuthBridge\AuthBridgeService;
use App\Service\QrService\QrService;

class SharedRequestIdentityService
{
    public function __construct(
        private readonly AuthBridgeService $authBridgeService,
        private readonly QrService $qrService,
    ) {
    }

    /**
     * @return array{toQrRead: array<string, mixed>, toNotification: VaultDeleteQrContentDTO}
     */
    public function generateRequestIdentity(array $validatedPayload, string $processKey): array
    {
        $identity = $this->authBridgeService->generateRequestIdentity($processKey);
        $getter = 'get' . ucfirst($processKey);
        $qrContent = $this->getQrContent($validatedPayload, $identity->getXExtensionAuthOne(), $identity->$getter());

        $identity->setQrCode($this->qrService->getQrCode($qrContent));

        if ($processKey === PayloadKeys::CREDENTIAL_DELETE) {
            return [
                'toQrRead' => $identity->toRemoveProcessArray(),
                'toNotification' => $qrContent,
            ];
        }

        return [
            'toQrRead' => $identity->toRegistrationProcessArray(),
            'toNotification' => $qrContent,
        ];
    }

    public function getQrContent(array $validatedPayload, ?string $mobilXExtensionAuth, ?string $processId): VaultDeleteQrContentDTO
    {
        return new VaultDeleteQrContentDTO(
            $validatedPayload['source'],
            $validatedPayload['targetId'],
            $validatedPayload['type'],
            $mobilXExtensionAuth,
            $processId,
        );
    }
}
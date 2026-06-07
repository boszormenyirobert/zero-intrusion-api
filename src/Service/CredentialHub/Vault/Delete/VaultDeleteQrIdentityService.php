<?php

declare(strict_types=1);

namespace App\Service\CredentialHub\Vault\Delete;

use App\Controller\CredentialHub\PayloadKeys;
use App\DTO\CredentialHub\Vault\Delete\VaultDeleteQrIdentityRequestDTO;
use App\Service\CredentialHub\SharedNotificationService;
use App\Service\CredentialHub\SharedPayloadService;
use App\Service\CredentialHub\SharedRequestIdentityService;
use App\Service\AuthBridge\AuthBridgeService;
use App\Service\QrService\QrService;
use App\Service\CredentialHub\Domain\Delete\DomainDeleteService;
use App\Service\CredentialHub\Shared\QrContentValidationService;
use Psr\Log\LoggerInterface;

class VaultDeleteQrIdentityService
{
    public function __construct(
        private readonly AuthBridgeService $authBridgeService,
        private readonly QrService $qrService,
        private readonly DomainDeleteService $domainDeleteService,
        private readonly SharedNotificationService $sharedNotificationService,
        private readonly QrContentValidationService $qrContentValidationService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function handle(VaultDeleteQrIdentityRequestDTO $request): ?array
    {
        $identity = $this->authBridgeService->generateRequestIdentity('application-delete');   

        $qrContent = $this->domainDeleteService->getQrContent(
            $identity->getXExtensionAuthOne(),                         
            $request->getType(),
            $request->getSource(),
            $request->getTargetId(),            
            $identity->getSessionId(),
            ""
        );

        $this->qrContentValidationService->validateOrFail($qrContent, 'application-delete');

        $identity->setQrCode($this->qrService->getQrCode($qrContent));

        $this->sharedNotificationService->sendFcmNotification('vaultDelete', $request->getUserPublicId(), $qrContent);
        
        return $identity->toRemoveProcessArray();
    }


    public function handleOriginal(Request $request): ?array
    {
        $process = $this->sharedPayloadService->getProcessId($request, PayloadKeys::VAULT_DELETE_QR_IDENTITY, true);

        if (!$process) {
            return null;
        }

        $identity = $this->sharedRequestIdentityService->generateRequestIdentity($process, PayloadKeys::VAULT_DELETE_PROCESS_ID);

        if (isset($process['userPublicId'], $identity['toNotification']) && $process['userPublicId'] && $identity['toNotification']) {
            $this->sharedNotificationService->sendFcmNotification('vaultDelete', $process['userPublicId'], $identity['toNotification']);
        }

        return (array) $identity['toQrRead'];
    }
}
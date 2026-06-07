<?php

declare(strict_types=1);

namespace App\Service\CredentialHub\Domain\Delete;

use App\Service\CredentialHub\Domain\Delete\DomainDeleteService;
use App\DTO\CredentialHub\Domain\Delete\DomainDeleteQrIdentityRequestDTO;
use App\Service\AuthBridge\AuthBridgeService;
use App\Service\CredentialHub\SharedNotificationService;
use App\Service\CredentialHub\Shared\QrContentValidationService;
use App\Service\QrService\QrService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class DomainDeleteQrIdentityService
{
    public function __construct(
        private readonly AuthBridgeService $authBridgeService,
        private readonly QrService $qrService,
        private readonly DomainDeleteService $domainDeleteService,
        private readonly SharedNotificationService $sharedNotificationService,
        private readonly QrContentValidationService $qrContentValidationService,
        private readonly ValidatorInterface $validator,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function handle(DomainDeleteQrIdentityRequestDTO $request): array
    {
        $identity = $this->authBridgeService->generateRequestIdentity('domain-delete');   
        
        $qrContent = $this->domainDeleteService->getQrContent(
            $identity->getXExtensionAuthOne(),                
            $request->getType(),
            $request->getSource(),
            $request->getTargetId(),            
            $identity->getSessionId(),
            $request->getDomain()
        );
        $this->qrContentValidationService->validateOrFail($qrContent, 'domain-delete');

        $identity->setQrCode($this->qrService->getQrCode($qrContent));

        $this->sharedNotificationService->sendFcmNotification('domainDelete', $request->getUserPublicId(), $qrContent);
        
        return $identity->toRemoveProcessArray();
    }
}
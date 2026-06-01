<?php

declare(strict_types=1);

namespace App\Service\CredentialHub\Domain\Delete;

use App\Service\CredentialHub\Domain\Delete\DomainDeleteService;
use App\DTO\CredentialHub\Domain\Delete\DomainDeleteQrIdentityRequestDTO;
use App\Service\AuthBridge\AuthBridgeService;
use App\Service\CredentialHub\DeferredFcmNotificationQueue;
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
        private readonly DeferredFcmNotificationQueue $deferredFcmNotificationQueue,
        private readonly QrContentValidationService $qrContentValidationService,
        private readonly ValidatorInterface $validator,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function handle(DomainDeleteQrIdentityRequestDTO $request): array
    {
        $identity = $this->authBridgeService->generateRequestIdentity('sessionId');   
        
        $qrContent = $this->domainDeleteService->getQrContent(
            $identity->getXExtensionAuthOne(),    
            $request->getDomain(),
            $request->getType(),
            $request->getSource(),
            $request->getTargetId(),            
            $identity->getSessionId(),
        );
        $this->qrContentValidationService->validateOrFail($qrContent, 'domain-delete');

        $identity->setQrCode($this->qrService->getQrCode($qrContent));

        $this->deferredFcmNotificationQueue->enqueue('domainDelete', $request->getUserPublicId(), $qrContent);
        
        return $identity->toRemoveProcessArray();
    }
}
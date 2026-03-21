<?php

namespace App\Controller\CredentialHub\Shared;

use App\DTO\QR\SharedRegistrationQrDTO;
use App\DTO\QR\OneTouchDTO;
use Psr\Log\LoggerInterface;
use App\Service\AuthBridge\AuthBridgeService;
class SharedRegistrationService
{
    public function __construct(
        private LoggerInterface $logger,
        private AuthBridgeService $authBridgeService
    ) {}    
    public function getQrContent($validatedPayload, $mobilXExtensionAuth, $processId): SharedRegistrationQrDTO
    {
        return new SharedRegistrationQrDTO(
            $processId,
            $mobilXExtensionAuth,
            $validatedPayload->type,
            $validatedPayload->source,
            $validatedPayload->isNew,
            $validatedPayload->userPublicId ?? null,
            $validatedPayload->targetId ?? null
        );
    }

    public function getExtendedQrContent($type, $qrContent, $validatedPayload)
    {
        if ($type === 'registration-domain') {
            $domain = $validatedPayload->domain;
            $qrContent->setDomain($domain);
        } else if ($type === 'registration-application') {
            $application = $validatedPayload->application;
            $qrContent->setApplication($application);
        }
        return $qrContent;
    }

    public function getOneTouchQrContent($validatedPayload, $mobilXExtensionAuth, $processId): OneTouchDTO
    {
        return new OneTouchDTO(
            $processId,
            $mobilXExtensionAuth,
            $validatedPayload->type,
            $validatedPayload->source,
            null,
            null
        );
    }
    
    public function saveUserCredentialInAuthBridge($validatedPayload, $registrationProcessId){
            $userCredential = [
            'userName' => $validatedPayload->userName,
            'userPassword' => $validatedPayload->userPassword,
            'description' => $validatedPayload->description,
        ];
        $this->authBridgeService->saveUserCredentialInAuthBridge($userCredential, $registrationProcessId);        
    }
    
    public function getUserCredentialFromAuthBridge($processId){
        return $this->authBridgeService->getUserCredentialFromAuthBridge($processId);
    }
}

<?php

namespace App\Controller\CredentialHub\Shared;

use App\DTO\QR\SharedRegistrationQrDTO;
use Psr\Log\LoggerInterface;

class SharedRegistrationService
{
    public function __construct(
        private LoggerInterface $logger
    ) {}    
    public function getQrContent($validatedPayload, $mobilXExtensionAuth, $processId): SharedRegistrationQrDTO
    {
        return new SharedRegistrationQrDTO(
            $validatedPayload->userName,
            $validatedPayload->userPassword,
            $processId,
            $mobilXExtensionAuth,
            $validatedPayload->type,
            $validatedPayload->source,
            $validatedPayload->isNew,
            $validatedPayload->description,
            $validatedPayload->userPublicId,
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
}

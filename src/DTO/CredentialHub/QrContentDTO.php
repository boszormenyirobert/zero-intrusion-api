<?php

namespace App\DTO\CredentialHub;

use Symfony\Component\Validator\Constraints as Assert;
use App\DTO\QR\QrInterface;
use App\DTO\CredentialHub\ExtensionCredentialResponseDTO;

class QrContentDTO implements QrInterface
{
    public ?string $domain;

    public ?string $domainProcessId = "";
    public ?string $applicationProcessId = "";

    // public ?string $xExtensionAuthOne;

    public ?string $type;

    public ?string $source;

    public ?string $iv;

    public ?string $publicKey;
   
    public ?string $qrCacheKey;

    public ?string $credentialCacheKey = "";

    public function __construct(
        ExtensionCredentialResponseDTO $identity        
    ) {
        $this->domainProcessId =  $identity->getDomainProcessId() ?? null;
        $this->applicationProcessId = $identity->getApplicationProcessId() ?? null;
        $this->domain = $identity->getDomain() ?? null;

        $this->type = $identity->getType();
        $this->source = $identity->getSource();
        $this->publicKey = $identity->getPublicKey();
        $this->qrCacheKey =$identity->getQrCacheKey();
    }

    public function setCredentialCacheKey(string $credentialCacheKey): void
    {
        $this->credentialCacheKey = $credentialCacheKey;
    }

    public function toNotificationDomain(): array
    {
        return [
            'domain' => $this->domain,
            'qrCacheKey' => $this->qrCacheKey,
            'credentialCacheKey' => $this->credentialCacheKey,
            'domainProcessId' => $this->domainProcessId,
            'type' => $this->type,
            'source' => $this->source,
            'publicKey' => $this->publicKey          
        ];
    }

    public function toNotificationApplication(): array
    {
        return [
            'applicationProcessId' => $this->applicationProcessId,
            'qrCacheKey' => $this->qrCacheKey,
            'credentialCacheKey' => $this->credentialCacheKey,
            'type' => $this->type,
            'source' => $this->source,
            'publicKey' => $this->publicKey          
        ];
    }    
}
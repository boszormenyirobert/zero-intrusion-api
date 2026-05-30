<?php

namespace App\DTO\CredentialHub;

use Symfony\Component\Validator\Constraints as Assert;
use App\DTO\QR\QrInterface;
use App\DTO\CredentialHub\ExtensionCredentialResponseDTO;

class QrContentDTO implements QrInterface
{
    public ?string $domain;

    public ?string $domainProcessId = "";
    public ?string $sessionId = "";

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
        $this->sessionId = $identity->getsessionId() ?? null;
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

    private function baseNotificationPayload(): array
    {
        return [
            'qrCacheKey' => $this->qrCacheKey,
            'credentialCacheKey' => $this->credentialCacheKey,
            'type' => $this->type,
            'source' => $this->source,
            'publicKey' => $this->publicKey,
        ];
    }

    public function toNotificationDomain(): array
    {
        return array_merge($this->baseNotificationPayload(), [
            'domain' => $this->domain,
            'domainProcessId' => $this->domainProcessId
        ]);
    }

    public function toNotificationApplication(): array
    {
        return array_merge($this->baseNotificationPayload(), [
            'sessionId' => $this->sessionId
        ]);
    }    
}
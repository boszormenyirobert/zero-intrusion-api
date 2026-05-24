<?php

namespace App\DTO\CredentialHub;

use Symfony\Component\Validator\Constraints as Assert;
use App\DTO\QR\QrInterface;
use App\DTO\CredentialHub\ExtensionCredentialResponseDTO;

class QrContentDTO implements QrInterface
{
    public ?string $domain;

    public ?string $domainProcessId;

    public ?string $xExtensionAuthOne;

    public ?string $type;

    public ?string $source;

    public ?string $iv;

    public ?string $publicKey;
   
    public ?string $qrCacheKey;

    public ?string $credentialCacheKey = "";

    public function __construct(
        ExtensionCredentialResponseDTO $identity        
    ) {
        $this->domain = $identity->getDomain();
        $this->domainProcessId =  $identity->getDomainProcessId();
        $this->type = $identity->getType();
        $this->source = $identity->getSource();
        $this->publicKey = $identity->getPublicKey();
        $this->qrCacheKey =$identity->getQrCacheKey();
    }

    public function setCredentialCacheKey(string $credentialCacheKey): void
    {
        $this->credentialCacheKey = $credentialCacheKey;
    }

    public function toNotification(): array
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
}
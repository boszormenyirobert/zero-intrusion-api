<?php
namespace App\DTO\QR;

use Symfony\Component\Validator\Constraints as Assert;
use App\DTO\QR\QrInterface;
use App\DTO\QR\CredentialHubIdentityDTO;

class DomainReadQrContentDTO implements QrInterface
{
    #[Assert\NotBlank]
    public ?string $domain;

    #[Assert\NotBlank]
    public ?string $domainProcessId;

    #[Assert\NotBlank]
    public ?string $xExtensionAuthOne;

    #[Assert\NotBlank]
    public ?string $type;

    #[Assert\NotBlank]
    public ?string $source;

    #[Assert\NotBlank]
    public ?string $iv;

    #[Assert\NotBlank]
    public ?string $publicKey;

    #[Assert\NotBlank]
    public ?string $qrCacheKey;

    public ?string $credentialCacheKey = "";

    public function __construct(
        ?string $domain,
        CredentialHubIdentityDTO $identity,        
        ?string $type,
        ?string $source
    ) {
        $this->domain = $domain;
        $this->domainProcessId =  $identity->getDomainProcessId();
        $this->xExtensionAuthOne = $identity->getXExtensionAuthOne();
        $this->type = $type;
        $this->source = $source;
        $this->iv = $identity->getIv();
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
            'qrCacheKey' => $this->qrCacheKey,
            'credentialCacheKey' => $this->credentialCacheKey,
            'domainProcessId' => $this->domainProcessId,
            'type' => $this->type,
            'source' => $this->source,
            'iv' => $this->iv,
            'publicKey' => $this->publicKey          
        ];
    }
}
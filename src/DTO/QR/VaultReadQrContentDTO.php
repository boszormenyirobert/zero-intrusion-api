<?php
namespace App\DTO\QR;

use Symfony\Component\Validator\Constraints as Assert;
use App\DTO\QR\QrInterface;
use App\DTO\QR\CredentialHubIdentityDTO;
use App\DTO\CredentialHub\Vault\Read\VaultReadQrIdentityRequestDTO;

class VaultReadQrContentDTO implements QrInterface
{
    #[Assert\NotBlank]
    public ?string $applicationProcessId;

    #[Assert\NotBlank]
    public ?string $type;

    #[Assert\NotBlank]
    public ?string $source;

    #[Assert\NotBlank]
    public ?string $xExtensionAuthOne;

    #[Assert\NotBlank]
    public ?string $iv;

    #[Assert\NotBlank]
    public ?string $publicKey;

    #[Assert\NotBlank]
    public ?string $qrCacheKey;

    public ?string $credentialCacheKey = "";

    public function __construct(
        ?CredentialHubIdentityDTO $identity,
        ?VaultReadQrIdentityRequestDTO $request,
    ) {
        $this->applicationProcessId = $identity->getApplicationProcessId();
        $this->type = $identity->getType();
        $this->source = $request->source;
        $this->xExtensionAuthOne = $identity->getXExtensionAuthOne();
        $this->iv = $identity->getIv();
        $this->publicKey = $identity->getPublicKey();
        $this->qrCacheKey =$identity->getQrCacheKey();
    }
    public function setCredentialCacheKey(string $credentialCacheKey): void
    {
        $this->credentialCacheKey = $credentialCacheKey;
    }
}

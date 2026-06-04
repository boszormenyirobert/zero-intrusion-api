<?php
namespace App\DTO\CredentialHub;

use Symfony\Component\Validator\Constraints as Assert;

class ExtensionCredentialResponseDTO
{
    public ?array $validCommunication = [];

    public ?string $createdAt;
    public ?string $xExtensionAuthOne;
    public ?string $xExtensionAuthTwo;
    public ?string $publicKey = null;
    public ?string $qrCacheKey = null;
    public ?string $type = null;
    public ?string $source = null; 
    public ?string $domain = null;

    public ?string $secret;
    public ?string $iv;

    public ?string $registrationProcessId = null;
    public ?string $sessionId = null;
    public ?string $domainProcessId = null;
    public ?string $qrCode;

    
    public function getValidCommunication(): array
    {
        return $this->validCommunication;
    }

    public function setValidCommunication(array $validCommunication): void
    {
        $this->validCommunication = $validCommunication;
    }

    public function getCreatedAt(): ?string
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?string $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getXExtensionAuthOne(): ?string
    {
        return $this->xExtensionAuthOne;
    }

    public function setXExtensionAuthOne(?string $xExtensionAuthOne): void
    {
        $this->xExtensionAuthOne = $xExtensionAuthOne;
    }

    public function getXExtensionAuthTwo(): ?string
    {
        return $this->xExtensionAuthTwo;
    }

    public function setXExtensionAuthTwo(?string $xExtensionAuthTwo): void
    {
        $this->xExtensionAuthTwo = $xExtensionAuthTwo;
    }

    /**
     * Get the value of secret
     */ 
    public function getSecret()
    {
        return $this->secret;
    }

    /**
     * Set the value of secret
     *
     * @return  self
     */ 
    public function setSecret($secret)
    {
        $this->secret = $secret;

        return $this;
    }

    /**
     * Get the value of iv
     */ 
    public function getIv()
    {
        return $this->iv;
    }

    /**
     * Set the value of iv
     *
     * @return  self
     */ 
    public function setIv($iv)
    {
        $this->iv = $iv;

        return $this;
    }

    /**
     * Get the value of registrationProcessId
     */ 
    public function getRegistrationProcessId()
    {
        return $this->registrationProcessId;
    }

    /**
     * Set the value of registrationProcessId
     *
     * @return  self
     */ 
    public function setRegistrationProcessId($registrationProcessId)
    {
        $this->registrationProcessId = $registrationProcessId;

        return $this;
    }

    /**
     * Get the value of sessionId
     */ 
    public function getSessionId()
    {
        return $this->sessionId;
    }

    /**
     * Set the value of sessionId
     *
     * @return  self
     */ 
    public function setSessionId($sessionId)
    {
        $this->sessionId = $sessionId;

        return $this;
    }

    /**
     * Get the value of domainProcessId
     */ 
    public function getDomainProcessId()
    {
        return $this->domainProcessId;
    }

    /**
     * Set the value of domainProcessId
     *
     * @return  self
     */ 
    public function setDomainProcessId($domainProcessId)
    {
        $this->domainProcessId = $domainProcessId;

        return $this;
    }

    public function toProcessArray(string $processKey): array
    {
        return match ($processKey) {
            'sessionId' => $this->buildProcessArray('sessionId', $this->sessionId),
            'domain-read' => $this->buildReadExtensionArray($this->sessionId),
            'vault-read' => $this->buildReadExtensionArray($this->sessionId),
            'one-touch' => $this->buildProcessArray('sessionId', $this->sessionId),
            'new-user-credential' => $this->buildReadExtensionArray($this->sessionId),
            
            'registrationProcessId' => $this->buildProcessArray('registrationProcessId', $this->registrationProcessId),
            'domainProcessId' => $this->buildProcessArray('domainProcessId', $this->domainProcessId),
            
            default => throw new \InvalidArgumentException(sprintf('Unsupported process key: %s', $processKey)),
        };
    }
    private function buildProcessArray(string $processKey, ?string $processId): array
    {
        return [
            'xExtensionAuthTwo' => $this->xExtensionAuthTwo,
            'iv' => $this->iv,
            $processKey => $processId, //  $processKey => sessionId
            'qrCode' => $this->qrCode,
            'qrCacheKey' => $this->qrCacheKey,
        ];
    }
    private function buildReadExtensionArray(string $processId): array
    {
        return [
              'sessionId' => $processId,   
              'qrCacheKey' => $this->qrCacheKey,
              'type' => $this->type
        ];
    }       

    public function toRegistrationProcessArray(): array
    {
        return $this->toProcessArray('registrationProcessId');
    }  
    public function toDomainProcessArray(): array
    {
        return $this->toProcessArray('domainProcessId');
    } 
    public function toRemoveProcessArray(): array
    {
        return $this->toProcessArray('sessionId');
    }      
    
    public function toApplicationProcessArray(): array
    {
        return $this->toProcessArray('sessionId');
    } 
    
    public function toOneTouchProcessArray(): array
    {
        return $this->toProcessArray('one-touch');
    }

    /**
     * Get the value of qrCode
     */ 
    public function getQrCode()
    {
        return $this->qrCode;
    }

    /**
     * Set the value of qrCode
     *
     * @return  self
     */ 
    public function setQrCode($qrCode)
    {
        $this->qrCode = $qrCode;

        return $this;
    }
    public function setPublicKey(?string $publicKey): void
    {
        $this->publicKey = $publicKey;
    }

    public function getPublicKey(): ?string
    {
        return $this->publicKey;
    }

    public function setQrCacheKey(?string $qrCacheKey): void
    {
        $this->qrCacheKey = $qrCacheKey;
    }

    public function getQrCacheKey(): ?string
    {
        return $this->qrCacheKey;
    }

    public function setType(?string $type): void
    {
        $this->type = $type;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setSource(?string $source): void
    {
        $this->source = $source;
    }

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function setDomain(?string $domain): void
    {
        $this->domain = $domain;
    }

    public function getDomain(): ?string
    {
        return $this->domain;
    }
}
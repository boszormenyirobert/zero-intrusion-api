<?php
namespace App\DTO\QR;

use App\DTO\Response\ResponseDataInterface;
use Symfony\Component\Validator\Constraints as Assert;
use App\DTO\QR\QrInterface;

class OneTouchDTO  implements QrInterface, ResponseDataInterface
{
    #[Assert\NotBlank]
    public array $validCommunication = [];

    public ?string $createdAt;
    public ?string $xExtensionAuthOne;
    public ?string $xExtensionAuthTwo;
    public ?string $type;
    public ?string $source;
    public ?string $userPublicId;
    public ?string $targetId;

    public ?string $secret;
    public ?string $iv;

    public ?string $registrationProcessId;
    public ?string $sessionId;
    public ?string $domainProcessId;
    public ?string $qrCode;

    public function __construct(
    string $sessionId,
    string $xExtensionAuthOne,
    string $type,
    ?string $source,
    ?string $userPublicId,
    ?string $targetId
) {
    $this->sessionId = $sessionId;
    $this->xExtensionAuthOne = $xExtensionAuthOne;
    $this->type = $type;
    $this->source = $source;
    $this->userPublicId = $userPublicId;
    $this->targetId = $targetId;
}

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
            'registrationProcessId' => $this->buildProcessArray('registrationProcessId', $this->registrationProcessId),
            'domainProcessId' => $this->buildProcessArray('domainProcessId', $this->domainProcessId),
            'sessionId' => $this->buildProcessArray('sessionId', $this->sessionId),
            'sessionId' => $this->buildProcessArray('sessionId', $this->sessionId),
            default => throw new \InvalidArgumentException(sprintf('Unsupported process key: %s', $processKey)),
        };
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
        return $this->toProcessArray('sessionId');
    }

    public function toResponseArray(): array
    {
        return $this->toOneTouchProcessArray();
    }

    private function buildProcessArray(string $processKey, ?string $processId): array
    {
        return [
            'validCommunication' => $this->validCommunication,
            'createdAt' => $this->createdAt,
            'xExtensionAuthOne' => $this->xExtensionAuthOne,
            'xExtensionAuthTwo' => $this->xExtensionAuthTwo,
            'secret' => $this->secret,
            'iv' => $this->iv,
            $processKey => $processId,
            'qrCode' => $this->qrCode
        ];
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
}
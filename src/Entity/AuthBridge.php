<?php

namespace App\Entity;

use App\Repository\AuthBridgeRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AuthBridgeRepository::class)]
class AuthBridge
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $userCredential = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $domainProcessId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $sessionId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $registrationProcessId = null;    

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $removeProcessId = null;         
    
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $oneTouchProcessId = null;  
    
    #[ORM\Column(length: 500, nullable: true)]
    private ?string $userIdentity = null; 

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $iv = null;

    #[ORM\Column(type: 'datetime')]
    private $createdAt;

    #[ORM\Column(length: 500)]
    private ?string $secret = null;

    #[ORM\Column(length: 100000, nullable: true)]
    private ?string $applications = null;

    #[ORM\Column(length: 5000, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $targetId = null;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $processState;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $publicId = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable(); 
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUserCredential(): ?string
    {
        return $this->userCredential;
    }

    public function setUserCredential(string $userCredential): static
    {
        $this->userCredential = $userCredential;

        return $this;
    }

    public function getIv(): ?string
    {
        return $this->iv;
    }

    public function setIv(string $iv): static
    {
        $this->iv = $iv;

        return $this;
    }

    public function getSecret(): ?string
    {
        return $this->secret;
    }

    public function setSecret(string $secret): static
    {
        $this->secret = $secret;

        return $this;
    }

    public function getsessionId(): ?string
    {
        return $this->sessionId;
    }

    public function setsessionId(?string $sessionId): static
    {
        $this->sessionId = $sessionId;

        return $this;
    }

    public function getDomainProcessId(): ?string
    {
        return $this->domainProcessId;
    }

    public function setDomainProcessId(string $domainProcessId): static
    {
        $this->domainProcessId = $domainProcessId;

        return $this;
    }
    
    public function getRegistrationProcessId(): ?string
    {
        return $this->registrationProcessId;
    }

    public function setRegistrationProcessId(string $registrationProcessId): static
    {
        $this->registrationProcessId = $registrationProcessId;

        return $this;
    }

    public function getRemoveProcessId(): ?string
    {
        return $this->removeProcessId;
    }

    public function setRemoveProcessId(string $removeProcessId): static
    {
        $this->removeProcessId = $removeProcessId;

        return $this;
    }    

    public function getApplications(): ?string
    {
        return $this->applications;
    }

    public function setApplications(?string $applications): static
    {
        $this->applications = $applications;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getTargetId(): ?string
    {
        return $this->targetId;
    }

    public function setTargetId(string $targetId): static
    {
        $this->targetId = $targetId;

        return $this;
    }

    public function isProcessState(): ?bool
    {
        return $this->processState;
    }

    public function setProcessState(bool $processState): self
    {
        $this->processState = $processState;

        return $this;
    }

    public function getPublicId(): ?string
    {
        return $this->publicId;
    }

    public function setPublicId(?string $publicId): static
    {
        $this->publicId = $publicId;

        return $this;
    } 

    public function getOneTouchProcessId(): ?string
    {
        return $this->oneTouchProcessId;
    }
    
    public function setOneTouchProcessId(string $oneTouchProcessId): static
    {
        $this->oneTouchProcessId = $oneTouchProcessId;

        return $this;
    }

    public function getUserIdentity(): ?string
    {
        return $this->userIdentity;
    }
    
    // Only publicId and e-mail should be stored here. No sensitive data.
    public function setUserIdentity(?string $userIdentity): static
    {
        $this->userIdentity = $userIdentity;

        return $this;
    }

    public function toOneTouchProcessArray(): array
    {
        $identity = $this->getUserIdentity() ? json_decode($this->getUserIdentity(), true) : null;

        return [
            'email' => $identity && isset($identity['email']) ? $identity['email'] : null,
            'publicId' => $identity && isset($identity['publicId']) ? $identity['publicId'] : null
        ];
    }

    public function toCacheArray(): array
    {
        return [
            'id' => $this->getId(),
            'domainProcessId' => $this->getDomainProcessId(),
            'sessionId' => $this->getsessionId(),
            'registrationProcessId' => $this->getRegistrationProcessId(),
            'removeProcessId' => $this->getRemoveProcessId(),
            'oneTouchProcessId' => $this->getOneTouchProcessId(),
            'iv' => $this->getIv(),
            'userIdentity' => $this->getUserIdentity() ? json_decode($this->getUserIdentity(), true) : null,
            'applications' => $this->getApplications(),
            'description' => $this->getDescription(),
            'targetId' => $this->getTargetId(),
            'processState' => $this->isProcessState(),
            'publicId' => $this->getPublicId(),
            'createdAt' => $this->getCreatedAt()?->format(DATE_ATOM),
        ];
    }

    public static function fromCacheArray(array $data): self
    {
        $authBridge = new self();

        if (!empty($data['domainProcessId'])) {
            $authBridge->setDomainProcessId($data['domainProcessId']);
        }

        if (!empty($data['sessionId'])) {
            $authBridge->setsessionId($data['sessionId']);
        }

        if (!empty($data['registrationProcessId'])) {
            $authBridge->setRegistrationProcessId($data['registrationProcessId']);
        }

        if (!empty($data['removeProcessId'])) {
            $authBridge->setRemoveProcessId($data['removeProcessId']);
        }

        if (!empty($data['oneTouchProcessId'])) {
            $authBridge->setOneTouchProcessId($data['oneTouchProcessId']);
        }

        if (!empty($data['iv'])) {
            $authBridge->setIv($data['iv']);
        }

        if (array_key_exists('applications', $data)) {
            $authBridge->setApplications($data['applications']);
        }

        if (array_key_exists('description', $data)) {
            $authBridge->setDescription($data['description']);
        }

        if (!empty($data['targetId'])) {
            $authBridge->setTargetId($data['targetId']);
        }

        if (array_key_exists('processState', $data) && $data['processState'] !== null) {
            $authBridge->setProcessState((bool) $data['processState']);
        }

        if (array_key_exists('publicId', $data)) {
            $authBridge->setPublicId($data['publicId']);
        }

        if (array_key_exists('userIdentity', $data) && $data['userIdentity'] !== null) {
            $authBridge->setUserIdentity(json_encode($data['userIdentity'], JSON_UNESCAPED_UNICODE));
        }

        if (!empty($data['createdAt'])) {
            $authBridge->setCreatedAt(new \DateTimeImmutable($data['createdAt']));
        }

        return $authBridge;
    }
}

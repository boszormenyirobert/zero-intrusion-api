<?php

namespace App\Entity;

use App\Repository\AuthBridgeRepository;
use DateTime;
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
    private ?string $applicationProcessId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $registrationProcessId = null;    

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $removeProcessId = null;           
    
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

    public function getApplicationProcessId(): ?string
    {
        return $this->applicationProcessId;
    }

    public function setApplicationProcessId(?string $applicationProcessId): static
    {
        $this->applicationProcessId = $applicationProcessId;

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
}

<?php

namespace App\Entity;

use App\Repository\AccessRegistryRepository;
use DateTime;
use Doctrine\ORM\Mapping as ORM;


#[ORM\Entity(repositoryClass: AccessRegistryRepository::class)]
#[ORM\Table(name: 'access_registry')]
class AccessRegistry
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 500, nullable: true)]
    private ?string $registrationProcessId = null;

    #[ORM\Column(type: 'boolean')]
    private bool $registrationState;

    #[ORM\Column(length: 800)]
    private ?string $publicId = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $corporateId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $domain = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $userCredential = null;

    #[ORM\Column(type: 'datetime')]
    private $createdAt;

    #[ORM\Column(length: 32)]
    private ?string $iv = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $application = null;

    #[ORM\Column(length: 5000, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 255)]
    private ?string $targetId = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRegistrationProcessId(): ?string
    {
        return $this->registrationProcessId;
    }

    public function setRegistrationProcessId(?string $registrationProcessId): self
    {
        $this->registrationProcessId = $registrationProcessId;

        return $this;
    }

    public function isRegistrationState(): ?bool
    {
        return $this->registrationState;
    }

    public function setRegistrationState(bool $registrationState): self
    {
        $this->registrationState = $registrationState;

        return $this;
    }

    public function getPublicId(): ?string
    {
        return $this->publicId;
    }

    public function setPublicId(string $publicId): static
    {
        $this->publicId = $publicId;

        return $this;
    }

    public function getCorporateId(): ?string
    {
        return $this->corporateId;
    }

    public function setCorporateId(?string $corporateId): static
    {
        $this->corporateId = $corporateId;

        return $this;
    }

    public function getDomain(): ?string
    {
        return $this->domain;
    }

    public function setDomain(?string $domain): static
    {
        $this->domain = $domain;

        return $this;
    }

    public function getUserCredential(): ?string
    {
        return $this->userCredential;
    }

    public function setUserCredential(string $userCredential, string $userEmail): static
    {
        $this->userCredential = $userCredential . ':' . $userEmail;

        return $this;
    }

    public function getCreateAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
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

    public function getApplication(): ?string
    {
        return $this->application;
    }

    public function setApplication(?string $application): static
    {
        $this->application = $application;

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
}

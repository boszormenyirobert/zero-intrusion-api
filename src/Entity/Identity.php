<?php

namespace App\Entity;

use App\Repository\IdentityRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: IdentityRepository::class)]
class Identity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 800)]
    private ?string $publicId = null;

    #[ORM\Column(length: 32)]
    private ?string $iv = null;

    #[ORM\Column(type: 'datetime')]
    private $createdAt;

    #[ORM\Column(length: 800)]
    private ?string $secret = null;

    #[ORM\Column(length: 800)]
    private ?string $credentialSecret = null;    

    #[ORM\Column(length: 800)]
    private ?string $privateId = null;

    #[ORM\Column(length: 255)]
    private ?string $email = null;

    #[ORM\Column(length: 255)]
    private ?string $phone = null;

    #[ORM\Column(nullable: true)]
    private ?bool $privacyPolicy = null;

    #[ORM\OneToOne(cascade: ['persist', 'remove'])]
    private ?BusinessServices $businessService = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $fcmToken = null;

    #[ORM\Column(length: 3000)]
    private ?string $nfcEncryptionKey = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getCreateAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function getId(): ?int
    {
        return $this->id;
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
    public function getCredentialSecret(): ?string
    {
        return $this->credentialSecret;
    }

    public function setCredentialSecret(string $credentialSecret): static
    {
        $this->credentialSecret = $credentialSecret;

        return $this;
    }

    public function getPrivateId(): ?string
    {
        return $this->privateId;
    }

    public function setPrivateId(string $privateId): static
    {
        $this->privateId = $privateId;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(string $phone): static
    {
        $this->phone = $phone;

        return $this;
    }

    public function isPrivacyPolicy(): ?bool
    {
        return $this->privacyPolicy;
    }

    public function setPrivacyPolicy(?bool $privacyPolicy): static
    {
        $this->privacyPolicy = $privacyPolicy;

        return $this;
    }

    public function getBusinessService(): ?BusinessServices
    {
        return $this->businessService;
    }

    public function setBusinessService(?BusinessServices $businessService): static
    {
        $this->businessService = $businessService;

        return $this;
    }

    public function getFcmToken(): ?array
    {
        return $this->fcmToken;
    }

    public function setFcmToken(?array $fcmToken): static
    {
        $this->fcmToken = $fcmToken;
        return $this;
    }

    public function getNfcEncryptionKey(): ?string
    {
        return $this->nfcEncryptionKey;
    }   
    public function setNfcEncryptionKey(string $nfcEncryptionKey): static
    {
        $this->nfcEncryptionKey = $nfcEncryptionKey;

        return $this;
    }
}

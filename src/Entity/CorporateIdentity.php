<?php

namespace App\Entity;

use App\Repository\CorporateIdentityRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CorporateIdentityRepository::class)]
class CorporateIdentity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 300)]
    private ?string $corporateIdKey = null;

    #[ORM\Column(length: 300)]
    private ?string $corporateIdSecret = null;

    #[ORM\Column(length: 10)]
    private ?string $state = null;

    #[ORM\Column(length: 32)]
    private ?string $iv = null;

    #[ORM\Column(length: 255)]
    private ?string $corporateId = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $callbackUserLogin = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $callbackUserRegistration = null;

    #[ORM\Column(length: 5000)]
    private ?string $sslPrivateKey = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $domain = null;

    #[ORM\ManyToOne(cascade: ['persist', 'remove'])]
    private ?BusinessServices $businessServices = null;

    #[ORM\Column(length: 5000, nullable: true)]
    private ?string $sslPublicKey = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCorporateIdKey(): ?string
    {
        return $this->corporateIdKey;
    }

    public function setCorporateIdKey(string $corporateIdKey): static
    {
        $this->corporateIdKey = $corporateIdKey;

        return $this;
    }

    public function getCorporateIdSecret(): ?string
    {
        return $this->corporateIdSecret;
    }

    public function setCorporateIdSecret(string $corporateIdSecret): static
    {
        $this->corporateIdSecret = $corporateIdSecret;

        return $this;
    }

    public function getState(): ?string
    {
        return $this->state;
    }

    public function setState(string $state): static
    {
        $this->state = $state;

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

    public function getCorporateId(): ?string
    {
        return $this->corporateId;
    }

    public function setCorporateId(string $corporateId): static
    {
        $this->corporateId = $corporateId;

        return $this;
    }

    public function getCallbackUserLogin(): ?string
    {
        return $this->callbackUserLogin;
    }

    public function setCallbackUserLogin(?string $callbackUserLogin): static
    {
        $this->callbackUserLogin = $callbackUserLogin;

        return $this;
    }

    public function getCallbackUserRegistration(): ?string
    {
        return $this->callbackUserRegistration;
    }

    public function setCallbackUserRegistration(?string $callbackUserRegistration): static
    {
        $this->callbackUserRegistration = $callbackUserRegistration;

        return $this;
    }

    public function getSslPrivateKey(): ?string
    {
        return $this->sslPrivateKey;
    }

    public function setSslPrivateKey(string $sslPrivateKey): static
    {
        $this->sslPrivateKey = $sslPrivateKey;

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

    public function getBusinessServices(): ?BusinessServices
    {
        return $this->businessServices;
    }

    public function setBusinessServices(?BusinessServices $businessServices): static
    {
        $this->businessServices = $businessServices;

        return $this;
    }

    public function getSslPublicKey(): ?string
    {
        return $this->sslPublicKey;
    }

    public function setSslPublicKey(?string $sslPublicKey): static
    {
        $this->sslPublicKey = $sslPublicKey;

        return $this;
    }
}

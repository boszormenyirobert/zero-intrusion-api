<?php

namespace App\Entity;

use App\Repository\RestoreRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RestoreRepository::class)]
class Restore
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(nullable: true)]
    private ?int $pin = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $hash = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $publicId = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $privateId = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $secret = null;

    #[ORM\Column(nullable: true)]
    private ?bool $allow = null;

    #[ORM\Column(type: 'datetime')]
    private $createdAt;

    #[ORM\Column(length: 32)]
    private ?string $iv = null;

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

    public function getPin(): ?int
    {
        return $this->pin;
    }

    public function setPin(?int $pin): static
    {
        $this->pin = $pin;

        return $this;
    }

    public function getHash(): ?string
    {
        return $this->hash;
    }

    public function setHash(?string $hash): static
    {
        $this->hash = $hash;

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

    public function getPrivateId(): ?string
    {
        return $this->privateId;
    }

    public function setPrivateId(?string $privateId): static
    {
        $this->privateId = $privateId;

        return $this;
    }

    public function getSecret(): ?string
    {
        return $this->secret;
    }

    public function setSecret(?string $secret): static
    {
        $this->secret = $secret;

        return $this;
    }

    public function isAllow(): ?bool
    {
        return $this->allow;
    }

    public function setAllow(?bool $allow): static
    {
        $this->allow = $allow;

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
}

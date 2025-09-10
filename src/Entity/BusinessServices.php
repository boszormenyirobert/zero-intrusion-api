<?php

namespace App\Entity;

use App\Repository\BusinessServicesRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: BusinessServicesRepository::class)]
class BusinessServices
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?bool $passwordManager = null;

    #[ORM\Column]
    private ?bool $biometric = null;

    #[ORM\Column]
    private ?bool $basic = null;

    #[ORM\Column]
    private ?bool $plus = null;

    #[ORM\Column]
    private ?bool $pro = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function isPasswordManager(): ?bool
    {
        return $this->passwordManager;
    }

    public function setPasswordManager(bool $passwordManager): static
    {
        $this->passwordManager = $passwordManager;

        return $this;
    }

    public function isBiometric(): ?bool
    {
        return $this->biometric;
    }

    public function setBiometric(bool $biometric): static
    {
        $this->biometric = $biometric;

        return $this;
    }

    public function isBasic(): ?bool
    {
        return $this->basic;
    }

    public function setBasic(bool $basic): static
    {
        $this->basic = $basic;

        return $this;
    }

    public function isPlus(): ?bool
    {
        return $this->plus;
    }

    public function setPlus(bool $plus): static
    {
        $this->plus = $plus;

        return $this;
    }

    public function isPro(): ?bool
    {
        return $this->pro;
    }

    public function setPro(bool $pro): static
    {
        $this->pro = $pro;

        return $this;
    }
}

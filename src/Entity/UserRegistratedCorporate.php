<?php

namespace App\Entity;

use App\Repository\UserRegistratedCorporateRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserRegistratedCorporateRepository::class)]
class UserRegistratedCorporate
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $publicId = null;

    #[ORM\Column(length: 255)]
    private ?string $corporateId = null;

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

    public function getCorporateId(): ?string
    {
        return $this->corporateId;
    }

    public function setCorporateId(string $corporateId): static
    {
        $this->corporateId = $corporateId;

        return $this;
    }
}

<?php
namespace App\DTO\QR;

use Symfony\Component\Validator\Constraints as Assert;
use App\DTO\QR\QrInterface;

class SharedRegistrationQrDTO implements QrInterface
{
    #[Assert\NotBlank]
    public ?string $userName;

    #[Assert\NotBlank]
    public ?string $userPassword;

    #[Assert\NotBlank]
    public ?string $registrationProcessId;

    #[Assert\NotBlank]
    public ?string $xExtensionAuthOne;

    #[Assert\NotBlank]
    public ?string $type;

    #[Assert\NotBlank]
    public ?string $source;

    #[Assert\NotNull]
    public ?bool $isNew;

    public ?string $description;
    
    #[Assert\Length(max: 255)]
    public ?string $domain = null;    

    #[Assert\Length(max: 255)]
    public ?string $application = null;    

    public function __construct(
        ?string $userName,
        ?string $userPassword,
        ?string $registrationProcessId,
        ?string $xExtensionAuthOne,
        ?string $type,
        ?string $source,
        ?bool $isNew,
        ?string $description
    ) {
        $this->userName = $userName;
        $this->userPassword = $userPassword;
        $this->registrationProcessId = $registrationProcessId;
        $this->xExtensionAuthOne = $xExtensionAuthOne;
        $this->type = $type;
        $this->source = $source;
        $this->isNew = $isNew;
        $this->description = $description;
    }

    public function setDomain(?string $domain): void
    {
        $this->domain = $domain;
    }

    public function setApplication(?string $application): void
    {
        $this->application = $application;
    }
}

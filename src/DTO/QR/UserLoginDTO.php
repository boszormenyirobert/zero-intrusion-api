<?php
namespace App\DTO\QR;

use Symfony\Component\Validator\Constraints as Assert;
use App\DTO\QR\QrInterface;

class UserLoginDTO implements QrInterface
{
    #[Assert\NotBlank]
    public ?string $domain;

    #[Assert\NotBlank]
    public ?string $domainProcessId;

    #[Assert\NotBlank]
    public ?string $xExtensionAuthOne;

    #[Assert\NotBlank]
    public ?string $type;

    #[Assert\NotBlank]
    public ?string $corporateId;

    #[Assert\NotBlank]
    public ?string $corporateAuthentication;

    #[Assert\NotBlank]
    public ?string $source;    

    public function __construct(
        ?string $domain,
        ?string $domainProcessId,
        ?string $xExtensionAuthOne,
        ?string $type,
        ?string $corporateId,
        ?string $corporateAuthentication,
        ?string $source
    ) {
        $this->domain = $domain;
        $this->domainProcessId = $domainProcessId;
        $this->xExtensionAuthOne = $xExtensionAuthOne;
        $this->type = $type;
        $this->corporateId = $corporateId;
        $this->corporateAuthentication = $corporateAuthentication;
        $this->source = $source;
    }
}
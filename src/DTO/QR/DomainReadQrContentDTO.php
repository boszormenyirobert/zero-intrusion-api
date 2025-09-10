<?php
namespace App\DTO\QR;

use Symfony\Component\Validator\Constraints as Assert;
use App\DTO\QR\QrInterface;

class DomainReadQrContentDTO implements QrInterface
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
    public ?string $source;

    #[Assert\NotBlank]
    public ?string $iv;

    public function __construct(
        ?string $domain,
        ?string $domainProcessId,
        ?string $xExtensionAuthOne,
        ?string $type,
        ?string $source,
        ?string $iv
    ) {
        $this->domain = $domain;
        $this->domainProcessId = $domainProcessId;
        $this->xExtensionAuthOne = $xExtensionAuthOne;
        $this->type = $type;
        $this->source = $source;
        $this->iv = $iv;
    }
}
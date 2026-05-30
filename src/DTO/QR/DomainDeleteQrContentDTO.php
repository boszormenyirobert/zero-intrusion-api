<?php
namespace App\DTO\QR;

use Symfony\Component\Validator\Constraints as Assert;
use App\DTO\QR\QrInterface;

class DomainDeleteQrContentDTO implements QrInterface
{
    #[Assert\NotBlank]
    public ?string $xExtensionAuthOne;

    #[Assert\NotBlank]
    public ?string $domain;

    #[Assert\NotBlank]
    public ?string $type;

    #[Assert\NotBlank]
    public ?string $source;

    #[Assert\NotBlank]
    public ?string $targetId;

    #[Assert\NotBlank]
    public ?string $sessionId;

    public function __construct(
        ?string $xExtensionAuthOne,
        ?string $domain,
        ?string $type,
        ?string $source,
        ?string $targetId,
        ?string $sessionId
    ) {
        $this->xExtensionAuthOne = $xExtensionAuthOne;
        $this->domain = $domain;
        $this->type = $type;
        $this->source = $source;
        $this->targetId = $targetId;
        $this->sessionId = $sessionId;
    }
}
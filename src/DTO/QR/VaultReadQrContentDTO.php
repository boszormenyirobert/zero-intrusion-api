<?php
namespace App\DTO\QR;

use Symfony\Component\Validator\Constraints as Assert;
use App\DTO\QR\QrInterface;

class VaultReadQrContentDTO implements QrInterface
{
    #[Assert\NotBlank]
    public ?string $applicationProcessId;

    #[Assert\NotBlank]
    public ?string $type;

    #[Assert\NotBlank]
    public ?string $source;

    #[Assert\NotBlank]
    public ?string $xExtensionAuthOne;

    #[Assert\NotBlank]
    public ?string $iv;

    public function __construct(
        ?string $applicationProcessId,
        ?string $type,
        ?string $source,
        ?string $xExtensionAuthOne,
        ?string $iv
    ) {
        $this->applicationProcessId = $applicationProcessId;
        $this->type = $type;
        $this->source = $source;
        $this->xExtensionAuthOne = $xExtensionAuthOne;
        $this->iv = $iv;
    }
}

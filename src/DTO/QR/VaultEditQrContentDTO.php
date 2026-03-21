<?php
namespace App\DTO\QR;

use Symfony\Component\Validator\Constraints as Assert;
use App\DTO\QR\QrInterface;

class VaultEditQrContentDTO implements QrInterface
{
    #[Assert\NotBlank]
    public ?string $source;

    #[Assert\NotBlank]
    public ?string $targetId;

    #[Assert\NotBlank]
    public ?string $type;

    #[Assert\NotBlank]
    public ?string $xExtensionAuthOne;

    #[Assert\NotBlank]
    public ?string $registrationProcessId;

    #[Assert\NotBlank]
    public ?string $application;

    public function __construct(
        ?string $source,
        ?string $targetId,
        ?string $type,
        ?string $xExtensionAuthOne,
        ?string $registrationProcessId,
        ?string $application
    ) {
        $this->source = $source;
        $this->targetId = $targetId;
        $this->type = $type;
        $this->xExtensionAuthOne = $xExtensionAuthOne;
        $this->registrationProcessId = $registrationProcessId;
        $this->application = $application;
    }
}

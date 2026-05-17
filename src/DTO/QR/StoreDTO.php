<?php
namespace App\DTO\QR;

use Symfony\Component\Validator\Constraints as Assert;
use App\DTO\QR\QrInterface;

class StoreDTO implements QrInterface
{
    #[Assert\NotBlank]
    public ?string $domain;

    #[Assert\NotBlank]
    public ?string $userPublicId;

    public function __construct(
        ?string $domain,
        ?string $userPublicId,

    ) {
        $this->domain = $domain;
        $this->userPublicId = $userPublicId;
    }
}
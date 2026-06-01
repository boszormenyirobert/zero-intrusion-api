<?php

declare(strict_types=1);

namespace App\Service\CredentialHub\Shared;

interface ReadCredentialDecryptedStrategyInterface
{
    public function resolve(array $context): array;
}

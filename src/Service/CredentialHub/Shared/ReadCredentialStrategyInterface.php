<?php

declare(strict_types=1);

namespace App\Service\CredentialHub\Shared;

interface ReadCredentialStrategyInterface
{
    public function handle(array $payload): bool;
}

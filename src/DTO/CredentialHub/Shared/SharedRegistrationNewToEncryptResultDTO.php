<?php

declare(strict_types=1);

namespace App\DTO\CredentialHub\Shared;

final readonly class SharedRegistrationNewToEncryptResultDTO
{
    /**
     * @param array<mixed>|string $registrationProcessInit
     */
    public function __construct(
        public array|string $registrationProcessInit,
        public string $error,
    ) {
    }

    /**
     * @return array{registration_process_init: array<mixed>|string, error: string}
     */
    public function toArray(): array
    {
        return [
            'registration_process_init' => $this->registrationProcessInit,
            'error' => $this->error,
        ];
    }
}
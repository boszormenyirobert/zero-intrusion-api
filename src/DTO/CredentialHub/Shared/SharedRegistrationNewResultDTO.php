<?php

declare(strict_types=1);

namespace App\DTO\CredentialHub\Shared;

final readonly class SharedRegistrationNewResultDTO
{
    /**
     * @param array<mixed> $registrationProcessOne
     */
    public function __construct(
        public array $registrationProcessOne,
        public string $error,
    ) {
    }

    /**
     * @return array{registration_process_one: array<mixed>, error: string}
     */
    public function toArray(): array
    {
        return [
            'registration_process_one' => $this->registrationProcessOne,
            'error' => $this->error,
        ];
    }
}
<?php

declare(strict_types=1);

namespace App\DTO\CredentialHub\OneTouch;

final readonly class OneTouchIdentifierResultDTO
{
    public function __construct(
        public bool $process,
        public string $error,
    ) {
    }

    /**
     * @return array{process: bool, error: string}
     */
    public function toArray(): array
    {
        return [
            'process' => $this->process,
            'error' => $this->error,
        ];
    }
}
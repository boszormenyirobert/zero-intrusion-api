<?php

declare(strict_types=1);

namespace App\DTO\CredentialHub\OneTouch;

final readonly class OneTouchIdentifierResultDTO
{
    public function __construct(
        public bool $oneTouchProcess,
        public string $error,
    ) {
    }

    /**
     * @return array{one_touch_process: bool, error: string}
     */
    public function toArray(): array
    {
        return [
            'one_touch_process' => $this->oneTouchProcess,
            'error' => $this->error,
        ];
    }
}
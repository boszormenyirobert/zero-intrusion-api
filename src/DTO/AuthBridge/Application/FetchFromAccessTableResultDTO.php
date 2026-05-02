<?php

declare(strict_types=1);

namespace App\DTO\AuthBridge\Application;

final readonly class FetchFromAccessTableResultDTO
{
    /**
     * @param array<string, bool> $process
     * @param array<mixed>|false $response
     */
    public function __construct(
        public array $process,
        public array|false $response,
    ) {
    }

    /**
     * @return array{process: array<string, bool>, response: array<mixed>|false}
     */
    public function toArray(): array
    {
        return [
            'process' => $this->process,
            'response' => $this->response,
        ];
    }
}
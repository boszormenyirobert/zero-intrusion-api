<?php

namespace App\Service\AccessRegistry\DTO;

class DeleteApplicationDto
{
    public function __construct(
        public readonly string $removeProcessId,
        public readonly string $targetId,
    ) {}
}
<?php

declare(strict_types=1);

namespace App\DTO\Response;

interface ResponseDataInterface
{
    public function toResponseArray(): array;
}
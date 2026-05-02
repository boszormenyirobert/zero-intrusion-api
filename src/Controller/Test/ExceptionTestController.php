<?php

declare(strict_types=1);

namespace App\Controller\Test;

use App\Exception\EntityNotFoundException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ExceptionTestController
{
    public function runtime(): never
    {
        throw new \RuntimeException('database password exposed');
    }

    public function notFound(): never
    {
        throw new NotFoundHttpException('Route not found');
    }

    public function entityNotFound(): never
    {
        throw new EntityNotFoundException();
    }
}
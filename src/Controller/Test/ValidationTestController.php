<?php

declare(strict_types=1);

namespace App\Controller\Test;

use App\Attribute\RequireHmac;
use App\Attribute\RequireJson;
use Symfony\Component\HttpFoundation\JsonResponse;

final class ValidationTestController
{
    #[RequireJson]
    public function requireJson(): JsonResponse
    {
        return new JsonResponse(['success' => true, 'message' => 'json-ok']);
    }

    #[RequireHmac]
    public function requireHmac(): JsonResponse
    {
        return new JsonResponse(['success' => true, 'message' => 'hmac-ok']);
    }
}
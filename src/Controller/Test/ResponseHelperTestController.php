<?php

declare(strict_types=1);

namespace App\Controller\Test;

use App\Helper\ResponseHelper;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;

final class ResponseHelperTestController extends AbstractController
{
    public function __construct(
        private readonly ResponseHelper $responseHelper,
    ) {
    }

    public function success(): JsonResponse
    {
        return $this->responseHelper->createSuccessResponse([
            'process' => true,
            'payload' => [
                'id' => 'public-1',
            ],
        ]);
    }

    public function successWithFalseFlag(): JsonResponse
    {
        return $this->responseHelper->createSuccessResponse([
            'success' => false,
            'payload' => [
                'id' => 'public-1',
            ],
        ]);
    }

    public function process(): JsonResponse
    {
        return $this->responseHelper->createProcessResponse('process-error');
    }

    public function exception(): JsonResponse
    {
        return $this->responseHelper->handleException(new \RuntimeException('boom'));
    }
}
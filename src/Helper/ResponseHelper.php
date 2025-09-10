<?php

namespace App\Helper;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Psr\Log\LoggerInterface;

class ResponseHelper
{
    public function __construct(
        private LoggerInterface $logger
    ) {}

    public function createSuccessResponse(array $data): JsonResponse
    {        
        return new JsonResponse(array_merge(['success' => true], $data));
    }

    public function createErrorResponse(string $errorMessage, int $statusCode = Response::HTTP_BAD_REQUEST): JsonResponse
    {
        $this->logger->critical($errorMessage);
        return new JsonResponse([
            'success' => false,
            'error' => $errorMessage
        ], $statusCode);
    }

    public function createProcessResponse(string $errorMessage): JsonResponse
    {
        $this->logger->critical($errorMessage);
        return new JsonResponse([
            'success' => false,
            'error' => $errorMessage
        ], 200);
    }

    public function handleException(\Exception $e, array $context = []): JsonResponse
    {
        $this->logger->critical($e->getMessage());
        $this->logger->error('An error occurred', array_merge([
            'error' => $e->getMessage()
        ], $context));

        return $this->createErrorResponse('Invalid payload or missing required data.');
    }
}

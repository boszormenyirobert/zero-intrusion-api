<?php

declare(strict_types=1);

namespace App\Helper;

use App\DTO\Response\ResponseDataInterface;
use App\Http\ApiErrorResponseFactory;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ResponseHelper
{
    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly ApiErrorResponseFactory $apiErrorResponseFactory = new ApiErrorResponseFactory(),
    ) {
    }

    public function createSuccessResponse(array|ResponseDataInterface $data): JsonResponse
    {
        if ($data instanceof ResponseDataInterface) {
            $data = $data->toResponseArray();
        }

        $responseData = [
            'process' => false,
            'validation' => false,
            'process_check' => false,
            'success' => true,
        ];

        foreach ($data as $key => $value) {
            $responseData[$key] = $this->normalizeValue($value);
        }

        $responseData['success'] = true;

        return new JsonResponse($responseData);
    }

    public function createErrorResponse(string $errorMessage, int $statusCode = Response::HTTP_BAD_REQUEST): JsonResponse
    {
        $this->logger->critical($errorMessage);

        return $this->buildErrorResponse($errorMessage, $statusCode);
    }

    public function createProcessResponse(string $errorMessage): JsonResponse
    {
        $this->logger->critical($errorMessage);

        return $this->buildErrorResponse($errorMessage, Response::HTTP_OK);
    }

    public function handleException(\Exception $e, array $context = []): JsonResponse
    {
        $this->logger->critical($e->getMessage());
        $this->logger->error('An error occurred', array_merge([
            'error' => $e->getMessage()
        ], $context));

        return $this->buildErrorResponse('Invalid payload or missing required data.');
    }

    private function normalizeValue(mixed $value): mixed
    {
        if ($value instanceof ResponseDataInterface) {
            return $value->toResponseArray();
        }

        if (is_object($value) && method_exists($value, 'toDomainStateArray')) {
            return $value->toDomainStateArray();
        }

        if (is_object($value) && method_exists($value, 'toArray')) {
            return $value->toArray();
        }

        if ($value instanceof \JsonSerializable) {
            return $value->jsonSerialize();
        }

        return $value;
    }

    private function buildErrorResponse(string $errorMessage, int $statusCode = Response::HTTP_BAD_REQUEST): JsonResponse
    {
        return $this->apiErrorResponseFactory->create($errorMessage, $statusCode);
    }
}

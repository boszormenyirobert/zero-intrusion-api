<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Attribute\RequireJson;
use App\Helper\UtilityHelper;
use App\Http\ApiErrorResponseFactory;
use Psr\Log\LoggerInterface;
use ReflectionMethod;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class JsonValidationListener
{
    private const INVALID_CONTENT_TYPE_ERROR = 'Invalid Content-Type, expected application/json';

    public function __construct(
        private readonly LoggerInterface $logger,
        private readonly ApiErrorResponseFactory $apiErrorResponseFactory,
    ) {
    }

    public function onKernelController(ControllerEvent $event): void
    {
        if ($event->getRequestType() !== HttpKernelInterface::MAIN_REQUEST) {
            return;
        }

        $controller = $event->getController();
        if (!is_array($controller)) {
            return;
        }

        [$controllerObject, $methodName] = $controller;
        $refMethod = new ReflectionMethod($controllerObject, $methodName);

        $attributes = $refMethod->getAttributes(RequireJson::class);
        if (empty($attributes)) {
            return;
        }

        $request = $event->getRequest();
        $contentType = $request->headers->get('Content-Type', '');

        if (!str_contains($contentType, 'application/json')) {
            $this->setErrorController($event, self::INVALID_CONTENT_TYPE_ERROR, 415);

            return;
        }

        try {
            $result = UtilityHelper::validateJsonFormat($request);
        } catch (\Throwable $exception) {
            $this->logger->critical('JSON validation failed.', ['exception' => $exception::class]);
            $this->setErrorController($event, 'Invalid JSON payload', 400);

            return;
        }

        if (isset($result['error'])) {
            $this->logger->critical('JSON validation failed.', ['error' => $result['error']]);
            $this->setErrorController($event, 'Invalid JSON payload', 400);

            return;
        }

        $request->attributes->set('json_payload', $result);
    }

    private function setErrorController(ControllerEvent $event, string $message, int $statusCode): void
    {
        $event->setController(fn() => $this->apiErrorResponseFactory->create($message, $statusCode));
    }
}

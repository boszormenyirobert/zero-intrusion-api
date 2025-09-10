<?php

namespace App\EventListener;

use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use App\Helper\UtilityHelper;
use ReflectionMethod;
use Psr\Log\LoggerInterface;

class JsonValidationListener
{
    private LoggerInterface $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
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
        $refMethod = new \ReflectionMethod($controllerObject, $methodName);

        $attributes = $refMethod->getAttributes(\App\Attribute\RequireJson::class);
        if (empty($attributes)) {
            return;
        }

        $request = $event->getRequest();


      //  $result = UtilityHelper::validateJsonFormat($request);        

        $contentType = $request->headers->get('Content-Type', '');

        if (!str_contains($contentType, 'application/json')) {
            $event->setController(fn() => new JsonResponse([
                'error' => 'Invalid Content-Type, expected application/json',
            ], 415)); // 415 Unsupported Media Type
            return;
        }

        try {
            $result = UtilityHelper::validateJsonFormat($request);           
        } catch (\Throwable $e) {
            $event->setController(fn() => new JsonResponse([
                'error' => 'JSON validation failed: ' . $e->getMessage()
            ], 400));
            return;
        }

        if (isset($result['error'])) {
            $this->logger->critical(json_encode("Something went wrong"));
            $event->setController(fn() => new JsonResponse([
                'error' => 'Json validation error: 400 Bad Request : ' . $result['error']
            ], 400));
            return;
        }

        $request->attributes->set('json_payload', $result);
    }
}

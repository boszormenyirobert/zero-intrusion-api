<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use App\Exception\EntityNotFoundException;
use App\Exception\InvalidPropertyException;
use App\Exception\InvalidInputException;
use App\Exception\MissingKeyException;

class ExceptionSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => 'onKernelException',
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        switch (true) {
            case $exception instanceof EntityNotFoundException:
                $response = new JsonResponse(['error' => 'Entity not found'], 404);
                break;

            case $exception instanceof InvalidPropertyException:
                $response = new JsonResponse(['error' => 'Entity has invalid property value'], 422);
                break;

            case $exception instanceof InvalidInputException:
                $response = new JsonResponse(['error' => 'Invalid input'], 400);
                break;

            case $exception instanceof MissingKeyException:
                $response = new JsonResponse(['error' => 'Missing expected key in array'], 400);
                break;

            default:
                $response = new JsonResponse(['error' => $exception->getMessage()], 500);
                break;
        }

        $event->setResponse($response);
    }
}

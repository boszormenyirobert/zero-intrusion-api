<?php

namespace App\EventSubscriber;

use App\Exception\EntityNotFoundException;
use App\Exception\InvalidInputException;
use App\Exception\InvalidPropertyException;
use App\Exception\MissingKeyException;
use App\Http\ApiErrorResponseFactory;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

class ExceptionSubscriber implements EventSubscriberInterface
{
    private const ERROR_INTERNAL_SERVER = 'Internal Server Error';

    public function __construct(
        private readonly ApiErrorResponseFactory $apiErrorResponseFactory = new ApiErrorResponseFactory(),
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => 'onKernelException',
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        $message = self::ERROR_INTERNAL_SERVER;
        $statusCode = 500;

        switch (true) {
            case $exception instanceof EntityNotFoundException:
                $message = 'Entity not found';
                $statusCode = 404;
                break;

            case $exception instanceof InvalidPropertyException:
                $message = 'Entity has invalid property value';
                $statusCode = 422;
                break;

            case $exception instanceof InvalidInputException:
                $message = 'Invalid input';
                $statusCode = 400;
                break;

            case $exception instanceof MissingKeyException:
                $message = 'Missing expected key in array';
                $statusCode = 400;
                break;

            case $exception instanceof HttpExceptionInterface:
                $statusCode = $exception->getStatusCode();
                $message = $this->resolveSafeHttpMessage($exception->getMessage(), $statusCode);
                break;

            default:
                break;
        }

        $event->setResponse($this->apiErrorResponseFactory->create($message, $statusCode));
    }

    private function resolveSafeHttpMessage(string $message, int $statusCode): string
    {
        if ($message !== '' && $statusCode >= 400 && $statusCode < 500) {
            return $message;
        }

        if ($statusCode >= 400 && $statusCode < 500) {
            return 'Request failed';
        }

        return self::ERROR_INTERNAL_SERVER;
    }
}

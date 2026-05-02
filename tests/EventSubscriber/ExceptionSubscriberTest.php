<?php

declare(strict_types=1);

namespace App\Tests\EventSubscriber;

use App\EventSubscriber\ExceptionSubscriber;
use App\Exception\EntityNotFoundException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class ExceptionSubscriberTest extends TestCase
{
    public function testEntityNotFoundExceptionReturnsExpectedJsonPayload(): void
    {
        $subscriber = new ExceptionSubscriber();
        $event = $this->createEvent(new EntityNotFoundException());

        $subscriber->onKernelException($event);

        self::assertSame(404, $event->getResponse()?->getStatusCode());
        self::assertSame(['success' => false, 'error' => 'Entity not found'], json_decode((string) $event->getResponse()?->getContent(), true));
    }

    public function testNotFoundHttpExceptionPreservesClientSafeStatusCode(): void
    {
        $subscriber = new ExceptionSubscriber();
        $event = $this->createEvent(new NotFoundHttpException('Route not found'));

        $subscriber->onKernelException($event);

        self::assertSame(404, $event->getResponse()?->getStatusCode());
        self::assertSame(['success' => false, 'error' => 'Route not found'], json_decode((string) $event->getResponse()?->getContent(), true));
    }

    public function testUnexpectedExceptionsDoNotLeakInternalMessages(): void
    {
        $subscriber = new ExceptionSubscriber();
        $event = $this->createEvent(new \RuntimeException('database password exposed'));

        $subscriber->onKernelException($event);

        self::assertSame(500, $event->getResponse()?->getStatusCode());
        self::assertSame(['success' => false, 'error' => 'Internal Server Error'], json_decode((string) $event->getResponse()?->getContent(), true));
    }

    private function createEvent(\Throwable $exception): ExceptionEvent
    {
        return new ExceptionEvent(
            $this->createMock(HttpKernelInterface::class),
            Request::create('/api/test'),
            HttpKernelInterface::MAIN_REQUEST,
            $exception,
        );
    }
}
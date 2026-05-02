<?php

declare(strict_types=1);

namespace App\Tests\EventSubscriber;

use App\EventSubscriber\ExceptionSubscriber;
use App\Exception\InvalidInputException;
use App\Exception\InvalidPropertyException;
use App\Exception\MissingKeyException;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class ExceptionSubscriberAdditionalTest extends TestCase
{
    public function testInvalidInputExceptionMapsToBadRequest(): void
    {
        $event = $this->createEvent(new InvalidInputException('bad input'));
        (new ExceptionSubscriber())->onKernelException($event);

        self::assertSame(400, $event->getResponse()?->getStatusCode());
        self::assertSame(['success' => false, 'error' => 'Invalid input'], json_decode((string) $event->getResponse()?->getContent(), true));
    }

    public function testInvalidPropertyExceptionMapsToUnprocessableEntity(): void
    {
        $event = $this->createEvent(new InvalidPropertyException('bad property'));
        (new ExceptionSubscriber())->onKernelException($event);

        self::assertSame(422, $event->getResponse()?->getStatusCode());
        self::assertSame(['success' => false, 'error' => 'Entity has invalid property value'], json_decode((string) $event->getResponse()?->getContent(), true));
    }

    public function testMissingKeyExceptionMapsToBadRequest(): void
    {
        $event = $this->createEvent(new MissingKeyException('missing key'));
        (new ExceptionSubscriber())->onKernelException($event);

        self::assertSame(400, $event->getResponse()?->getStatusCode());
        self::assertSame(['success' => false, 'error' => 'Missing expected key in array'], json_decode((string) $event->getResponse()?->getContent(), true));
    }

    public function testHttpExceptionsUseSafeMessages(): void
    {
        $subscriber = new ExceptionSubscriber();

        $blankClientMessage = $this->createEvent(new BadRequestHttpException(''));
        $subscriber->onKernelException($blankClientMessage);
        self::assertSame(['success' => false, 'error' => 'Request failed'], json_decode((string) $blankClientMessage->getResponse()?->getContent(), true));

        $serverMessage = $this->createEvent(new HttpException(503, 'upstream details'));
        $subscriber->onKernelException($serverMessage);
        self::assertSame(['success' => false, 'error' => 'Internal Server Error'], json_decode((string) $serverMessage->getResponse()?->getContent(), true));
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
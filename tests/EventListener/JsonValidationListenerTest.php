<?php

declare(strict_types=1);

namespace App\Tests\EventListener;

use App\Attribute\RequireJson;
use App\EventListener\JsonValidationListener;
use App\Http\ApiErrorResponseFactory;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class JsonValidationListenerTest extends TestCase
{
    public function testOnKernelControllerRejectsInvalidContentType(): void
    {
        $listener = new JsonValidationListener($this->createMock(LoggerInterface::class), new ApiErrorResponseFactory());
        $controller = new class {
            #[RequireJson]
            public function handle(): JsonResponse
            {
                return new JsonResponse(['success' => true]);
            }
        };

        $event = $this->createEvent($controller, '{"key":"value"}', ['CONTENT_TYPE' => 'text/plain']);

        $listener->onKernelController($event);

        $resolvedController = $event->getController();
        self::assertIsCallable($resolvedController);

        $response = $resolvedController();
        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(415, $response->getStatusCode());
        self::assertSame(
            ['success' => false, 'error' => 'Invalid Content-Type, expected application/json'],
            json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR)
        );
    }

    public function testOnKernelControllerRejectsInvalidJsonPayload(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('critical');

        $listener = new JsonValidationListener($logger, new ApiErrorResponseFactory());
        $controller = new class {
            #[RequireJson]
            public function handle(): JsonResponse
            {
                return new JsonResponse(['success' => true]);
            }
        };

        $event = $this->createEvent($controller, '{invalid', ['CONTENT_TYPE' => 'application/json']);

        $listener->onKernelController($event);

        $resolvedController = $event->getController();
        self::assertIsCallable($resolvedController);

        $response = $resolvedController();
        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(400, $response->getStatusCode());
        self::assertSame(
            ['success' => false, 'error' => 'Invalid JSON payload'],
            json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR)
        );
    }

    public function testOnKernelControllerStoresDecodedJsonPayloadForValidRequest(): void
    {
        $listener = new JsonValidationListener($this->createMock(LoggerInterface::class), new ApiErrorResponseFactory());
        $controller = new class {
            #[RequireJson]
            public function handle(): JsonResponse
            {
                return new JsonResponse(['success' => true]);
            }
        };

        $event = $this->createEvent($controller, '{"key":"value"}', ['CONTENT_TYPE' => 'application/json']);

        $listener->onKernelController($event);

        self::assertSame(['key' => 'value'], $event->getRequest()->attributes->get('json_payload'));
        self::assertSame([$controller, 'handle'], $event->getController());
    }

    private function createEvent(object $controller, string $content, array $server = []): ControllerEvent
    {
        $request = Request::create('/json', 'POST', [], [], [], $server, $content);

        return new ControllerEvent(
            $this->createMock(HttpKernelInterface::class),
            [$controller, 'handle'],
            $request,
            HttpKernelInterface::MAIN_REQUEST,
        );
    }
}

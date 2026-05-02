<?php

declare(strict_types=1);

namespace App\Tests\EventListener;

use App\Attribute\RequireHmac;
use App\EventListener\HmacAnnotationListener;
use App\Service\Hmac\HmacValidator;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class HmacAnnotationListenerTest extends TestCase
{
    public function testInvokeThrowsBadRequestForInvalidJsonBody(): void
    {
        $validator = $this->createMock(HmacValidator::class);
        $validator->expects(self::never())->method('validate');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('critical')->with('Invalid JSON body');

        $listener = new HmacAnnotationListener($validator, $logger);
        $controller = new class {
            #[RequireHmac]
            public function handle(): JsonResponse
            {
                return new JsonResponse(['success' => true]);
            }
        };

        $event = $this->createEvent($controller, '{invalid');

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Invalid JSON body');

        $listener($event);
    }

    public function testInvokeThrowsBadRequestForMissingRequiredFields(): void
    {
        $validator = $this->createMock(HmacValidator::class);
        $validator->expects(self::never())->method('validate');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('critical')->with('Missing required fields');

        $listener = new HmacAnnotationListener($validator, $logger);
        $controller = new class {
            #[RequireHmac]
            public function handle(): JsonResponse
            {
                return new JsonResponse(['success' => true]);
            }
        };

        $event = $this->createEvent($controller, json_encode(['iv' => 'only-iv'], JSON_THROW_ON_ERROR));

        $this->expectException(BadRequestHttpException::class);
        $this->expectExceptionMessage('Missing required fields');

        $listener($event);
    }

    public function testInvokeDelegatesToValidatorForRequireHmacControllers(): void
    {
        $payload = [
            'zeroIntrusionProyApi' => 'encrypted-payload',
            'iv' => 'iv-value',
        ];
        $content = json_encode($payload, JSON_THROW_ON_ERROR);

        $validator = $this->createMock(HmacValidator::class);
        $validator
            ->expects(self::once())
            ->method('validate')
            ->with($content, 'HMAC client:signature:123', 'iv-value', $payload)
            ->willReturn(true);

        $listener = new HmacAnnotationListener($validator, $this->createMock(LoggerInterface::class));
        $controller = new class {
            #[RequireHmac]
            public function handle(): JsonResponse
            {
                return new JsonResponse(['success' => true]);
            }
        };

        $event = $this->createEvent($controller, $content, ['HTTP_X_AUTH' => 'HMAC client:signature:123']);

        $listener($event);

        self::assertSame([$controller, 'handle'], $event->getController());
    }

    public function testInvokeSkipsControllersWithoutRequireHmacAttribute(): void
    {
        $validator = $this->createMock(HmacValidator::class);
        $validator->expects(self::never())->method('validate');

        $listener = new HmacAnnotationListener($validator, $this->createMock(LoggerInterface::class));
        $controller = new class {
            public function handle(): JsonResponse
            {
                return new JsonResponse(['success' => true]);
            }
        };

        $event = $this->createEvent($controller, json_encode(['zeroIntrusionProyApi' => 'value', 'iv' => 'iv'], JSON_THROW_ON_ERROR));

        $listener($event);

        self::assertSame([$controller, 'handle'], $event->getController());
    }

    private function createEvent(object $controller, string $content, array $server = []): ControllerEvent
    {
        $request = Request::create('/hmac', 'POST', [], [], [], $server, $content);

        return new ControllerEvent(
            $this->createMock(HttpKernelInterface::class),
            [$controller, 'handle'],
            $request,
            HttpKernelInterface::MAIN_REQUEST,
        );
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\EventListener;

use App\Attribute\ExtensionHmac;
use App\Entity\AuthBridge;
use App\EventListener\HmacExtensionValidationListener;
use App\Http\ApiErrorResponseFactory;
use App\Repository\AuthBridgeRepository;
use App\Service\Crypters\CrypterService;
use App\Service\Hmac\ListenerHmacPolicy;
use App\Service\Hmac\ListenerPayloadResolver;
use App\Service\Payload\JsonPayloadDecoder;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class HmacExtensionValidationListenerTest extends TestCase
{
    public function testOnKernelControllerSetsErrorResponseForInvalidJsonBody(): void
    {
        $params = $this->createParameterBag();
        $repository = $this->createMock(AuthBridgeRepository::class);
        $repository->expects(self::never())->method('findOneBy');

        $listener = new HmacExtensionValidationListener(
            new CrypterService($params),
            $this->createMock(LoggerInterface::class),
            $repository,
            $this->createMock(EntityManagerInterface::class),
            $params,
            new ApiErrorResponseFactory(),
            new JsonPayloadDecoder(),
            new ListenerHmacPolicy($params, $this->createMock(LoggerInterface::class)),
            new ListenerPayloadResolver(new JsonPayloadDecoder(), new CrypterService($params)),
        );

        $controller = new class {
            #[ExtensionHmac]
            public function handle(): JsonResponse
            {
                return new JsonResponse(['success' => true]);
            }
        };

        $event = $this->createEvent($controller, '{invalid', 'domain_read_state');

        $listener->onKernelController($event);

        $resolvedController = $event->getController();
        self::assertIsCallable($resolvedController);

        $response = $resolvedController();
        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(400, $response->getStatusCode());
        self::assertSame(['success' => false, 'error' => 'Invalid JSON body'], json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR));
    }

    public function testOnKernelControllerSetsErrorResponseForUnknownPayloadType(): void
    {
        $params = $this->createParameterBag();
        $repository = $this->createMock(AuthBridgeRepository::class);
        $repository->expects(self::never())->method('findOneBy');

        $listener = new HmacExtensionValidationListener(
            new CrypterService($params),
            $this->createMock(LoggerInterface::class),
            $repository,
            $this->createMock(EntityManagerInterface::class),
            $params,
            new ApiErrorResponseFactory(),
            new JsonPayloadDecoder(),
            new ListenerHmacPolicy($params, $this->createMock(LoggerInterface::class)),
            new ListenerPayloadResolver(new JsonPayloadDecoder(), new CrypterService($params)),
        );

        $encryptedPayload = $this->encryptPayload($params, ['unknown_state' => ['processId' => 'process-123']]);
        $requestBody = json_encode([
            'zeroIntrusionProyApi' => $encryptedPayload,
            'iv' => 'ignored-for-listener',
        ], JSON_THROW_ON_ERROR);

        $controller = new class {
            #[ExtensionHmac]
            public function handle(): JsonResponse
            {
                return new JsonResponse(['success' => true]);
            }
        };

        $event = $this->createEvent($controller, $requestBody, 'unknown_state', ['HTTP_X_EXTENSION_AUTH' => 'HMAC any-value']);

        $listener->onKernelController($event);

        $resolvedController = $event->getController();
        self::assertIsCallable($resolvedController);

        $response = $resolvedController();
        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(400, $response->getStatusCode());
        self::assertSame(['success' => false, 'error' => 'Unknown payload type: unknown_state'], json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR));
    }

    public function testOnKernelControllerLeavesAnnotatedControllerUntouchedForValidRequest(): void
    {
        $params = $this->createParameterBag();
        $process = (new AuthBridge())
            ->setDomainProcessId('process-123')
            ->setCreatedAt(new \DateTimeImmutable());

        $repository = $this->createMock(AuthBridgeRepository::class);
        $repository
            ->expects(self::once())
            ->method('findOneBy')
            ->with(['domainProcessId' => 'process-123'])
            ->willReturn($process);

        $listener = new HmacExtensionValidationListener(
            new CrypterService($params),
            $this->createMock(LoggerInterface::class),
            $repository,
            $this->createMock(EntityManagerInterface::class),
            $params,
            new ApiErrorResponseFactory(),
            new JsonPayloadDecoder(),
            new ListenerHmacPolicy($params, $this->createMock(LoggerInterface::class)),
            new ListenerPayloadResolver(new JsonPayloadDecoder(), new CrypterService($params)),
        );

        $encryptedPayload = $this->encryptPayload($params, ['domain_read_state' => ['processId' => 'process-123']]);
        $requestBody = json_encode([
            'zeroIntrusionProyApi' => $encryptedPayload,
            'iv' => 'ignored-for-listener',
        ], JSON_THROW_ON_ERROR);

        $authHeader = 'HMAC ' . hash_hmac('sha1', 'extension-message|' . $process->getCreatedAt()?->getTimestamp(), 'extension-secret');

        $controller = new class {
            #[ExtensionHmac]
            public function handle(): JsonResponse
            {
                return new JsonResponse(['success' => true]);
            }
        };

        $originalController = [$controller, 'handle'];
        $event = $this->createEvent($controller, $requestBody, 'domain_read_state', ['HTTP_X_EXTENSION_AUTH' => $authHeader]);

        $listener->onKernelController($event);

        self::assertSame($originalController, $event->getController());
    }

    private function createEvent(object $controller, string $content, string $route, array $server = []): ControllerEvent
    {
        $request = Request::create('/extension', 'POST', [], [], [], $server, $content);
        $request->attributes->set('_route', $route);
        $request->attributes->set('_controller', $controller::class . '::handle');

        return new ControllerEvent(
            $this->createMock(HttpKernelInterface::class),
            [$controller, 'handle'],
            $request,
            HttpKernelInterface::MAIN_REQUEST,
        );
    }

    private function encryptPayload(ContainerBagInterface $params, array $payload): string
    {
        $crypter = new CrypterService($params);
        $crypter->setData($payload);

        return $crypter->encryptData();
    }

    private function createParameterBag(): ContainerBagInterface&ParameterBagInterface
    {
        return new class () implements ContainerBagInterface, ParameterBagInterface {
            public function all(): array
            {
                return [
                    'DATA_HASH_SECRET' => '12345678901234567890123456789012',
                    'EXTENSION_REGISTRATION_POOL_SECRET' => 'extension-secret',
                    'EXTENSION_REGISTRATION_POOL_MESSAGE' => 'extension-message',
                ];
            }

            public function resolve(): void
            {
            }

            public function resolveValue(mixed $value): mixed
            {
                return $value;
            }

            public function escapeValue(mixed $value): mixed
            {
                return $value;
            }

            public function unescapeValue(mixed $value): mixed
            {
                return $value;
            }

            public function add(array $parameters): void
            {
            }

            public function get(string $name): array|bool|string|int|float|\UnitEnum|null
            {
                return match ($name) {
                    'DATA_HASH_SECRET' => '12345678901234567890123456789012',
                    'EXTENSION_REGISTRATION_POOL_SECRET' => 'extension-secret',
                    'EXTENSION_REGISTRATION_POOL_MESSAGE' => 'extension-message',
                    default => null,
                };
            }

            public function set(string $name, array|bool|string|int|float|\UnitEnum|null $value): void
            {
            }

            public function has(string $name): bool
            {
                return in_array($name, ['DATA_HASH_SECRET', 'EXTENSION_REGISTRATION_POOL_SECRET', 'EXTENSION_REGISTRATION_POOL_MESSAGE'], true);
            }

            public function remove(string $name): void
            {
            }

            public function clear(): void
            {
            }
        };
    }
}

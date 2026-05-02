<?php

declare(strict_types=1);

namespace App\Tests\EventListener;

use App\Attribute\DesktopHmac;
use App\Entity\CorporateIdentity;
use App\EventListener\HmacDesktopValidationListener;
use App\Http\ApiErrorResponseFactory;
use App\Repository\AuthBridgeRepository;
use App\Repository\CorporateIdentityRepository;
use App\Service\Crypters\CrypterDatabaseService;
use App\Service\Crypters\CrypterService;
use App\Service\Hmac\DesktopHmacPolicy;
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

final class HmacDesktopValidationListenerTest extends TestCase
{
    public function testOnKernelControllerSetsErrorResponseForInvalidJsonBody(): void
    {
        $params = $this->createParameterBag();
        $listener = new HmacDesktopValidationListener(
            new CrypterService($params),
            $this->createMock(LoggerInterface::class),
            $this->createMock(AuthBridgeRepository::class),
            $this->createMock(EntityManagerInterface::class),
            $params,
            $this->createMock(CorporateIdentityRepository::class),
            $this->createMock(CrypterDatabaseService::class),
            new ApiErrorResponseFactory(),
            new JsonPayloadDecoder(),
            new DesktopHmacPolicy(),
            new ListenerPayloadResolver(new JsonPayloadDecoder(), new CrypterService($params)),
        );

        $controller = new class {
            #[DesktopHmac]
            public function handle(): JsonResponse
            {
                return new JsonResponse(['success' => true]);
            }
        };

        $event = $this->createEvent($controller, '{invalid', 'api_nfc_users');

        $listener->onKernelController($event);

        $resolvedController = $event->getController();
        self::assertIsCallable($resolvedController);

        $response = $resolvedController();
        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(400, $response->getStatusCode());
        self::assertSame(['success' => false, 'error' => 'Invalid JSON body'], json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR));
    }

    public function testOnKernelControllerSetsErrorResponseForInvalidHmacSignature(): void
    {
        $params = $this->createParameterBag();
        $corporateRepository = $this->createMock(CorporateIdentityRepository::class);
        $corporateRepository
            ->expects(self::once())
            ->method('findOneBy')
            ->with(['corporateId' => 'corp-123'])
            ->willReturn(new CorporateIdentity());

        $decryptedCorporate = (new CorporateIdentity())
            ->setCorporateIdKey('desktop-key')
            ->setCorporateIdSecret('desktop-secret');

        $crypterDatabaseService = $this->createMock(CrypterDatabaseService::class);
        $crypterDatabaseService
            ->expects(self::once())
            ->method('decryptFromDatabase')
            ->willReturn($decryptedCorporate);

        $listener = new HmacDesktopValidationListener(
            new CrypterService($params),
            $this->createMock(LoggerInterface::class),
            $this->createMock(AuthBridgeRepository::class),
            $this->createMock(EntityManagerInterface::class),
            $params,
            $corporateRepository,
            $crypterDatabaseService,
            new ApiErrorResponseFactory(),
            new JsonPayloadDecoder(),
            new DesktopHmacPolicy(),
            new ListenerPayloadResolver(new JsonPayloadDecoder(), new CrypterService($params)),
        );

        $timestamp = time();
        $encryptedPayload = $this->encryptPayload($params, [
            'api_nfc_users' => [
                'publicId' => 'corp-123',
                'timestamp' => $timestamp,
            ],
        ]);
        $requestBody = json_encode([
            'zeroIntrusionProyApi' => $encryptedPayload,
            'iv' => 'ignored-for-listener',
        ], JSON_THROW_ON_ERROR);

        $controller = new class {
            #[DesktopHmac]
            public function handle(): JsonResponse
            {
                return new JsonResponse(['success' => true]);
            }
        };

        $event = $this->createEvent($controller, $requestBody, 'api_nfc_users', ['HTTP_X_EXTENSION_AUTH' => 'invalid-signature']);

        $listener->onKernelController($event);

        $resolvedController = $event->getController();
        self::assertIsCallable($resolvedController);

        $response = $resolvedController();
        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(400, $response->getStatusCode());
        self::assertSame(['success' => false, 'error' => 'Invalid HMAC signature'], json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR));
    }

    public function testOnKernelControllerLeavesAnnotatedControllerUntouchedForValidRequest(): void
    {
        $params = $this->createParameterBag();
        $corporateRepository = $this->createMock(CorporateIdentityRepository::class);
        $corporateRepository
            ->expects(self::once())
            ->method('findOneBy')
            ->with(['corporateId' => 'corp-123'])
            ->willReturn(new CorporateIdentity());

        $decryptedCorporate = (new CorporateIdentity())
            ->setCorporateIdKey('desktop-key')
            ->setCorporateIdSecret('desktop-secret');

        $crypterDatabaseService = $this->createMock(CrypterDatabaseService::class);
        $crypterDatabaseService
            ->expects(self::once())
            ->method('decryptFromDatabase')
            ->willReturn($decryptedCorporate);

        $listener = new HmacDesktopValidationListener(
            new CrypterService($params),
            $this->createMock(LoggerInterface::class),
            $this->createMock(AuthBridgeRepository::class),
            $this->createMock(EntityManagerInterface::class),
            $params,
            $corporateRepository,
            $crypterDatabaseService,
            new ApiErrorResponseFactory(),
            new JsonPayloadDecoder(),
            new DesktopHmacPolicy(),
            new ListenerPayloadResolver(new JsonPayloadDecoder(), new CrypterService($params)),
        );

        $timestamp = time();
        $encryptedPayload = $this->encryptPayload($params, [
            'api_nfc_users' => [
                'publicId' => 'corp-123',
                'timestamp' => $timestamp,
            ],
        ]);
        $requestBody = json_encode([
            'zeroIntrusionProyApi' => $encryptedPayload,
            'iv' => 'ignored-for-listener',
        ], JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', 'desktop-key|' . $timestamp, 'desktop-secret');

        $controller = new class {
            #[DesktopHmac]
            public function handle(): JsonResponse
            {
                return new JsonResponse(['success' => true]);
            }
        };

        $originalController = [$controller, 'handle'];
        $event = $this->createEvent($controller, $requestBody, 'api_nfc_users', ['HTTP_X_EXTENSION_AUTH' => $signature]);

        $listener->onKernelController($event);

        self::assertSame($originalController, $event->getController());
    }

    public function testOnKernelControllerReturnsTimestampErrorWhenSignatureIsValidButExpired(): void
    {
        $params = $this->createParameterBag();
        $corporateRepository = $this->createMock(CorporateIdentityRepository::class);
        $corporateRepository
            ->expects(self::once())
            ->method('findOneBy')
            ->with(['corporateId' => 'corp-123'])
            ->willReturn(new CorporateIdentity());

        $decryptedCorporate = (new CorporateIdentity())
            ->setCorporateIdKey('desktop-key')
            ->setCorporateIdSecret('desktop-secret');

        $crypterDatabaseService = $this->createMock(CrypterDatabaseService::class);
        $crypterDatabaseService
            ->expects(self::once())
            ->method('decryptFromDatabase')
            ->willReturn($decryptedCorporate);

        $listener = new HmacDesktopValidationListener(
            new CrypterService($params),
            $this->createMock(LoggerInterface::class),
            $this->createMock(AuthBridgeRepository::class),
            $this->createMock(EntityManagerInterface::class),
            $params,
            $corporateRepository,
            $crypterDatabaseService,
            new ApiErrorResponseFactory(),
            new JsonPayloadDecoder(),
            new DesktopHmacPolicy(),
            new ListenerPayloadResolver(new JsonPayloadDecoder(), new CrypterService($params)),
        );

        $timestamp = time() - 301;
        $encryptedPayload = $this->encryptPayload($params, [
            'api_nfc_users' => [
                'publicId' => 'corp-123',
                'timestamp' => $timestamp,
            ],
        ]);
        $requestBody = json_encode([
            'zeroIntrusionProyApi' => $encryptedPayload,
            'iv' => 'ignored-for-listener',
        ], JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha256', 'desktop-key|' . $timestamp, 'desktop-secret');

        $controller = new class {
            #[DesktopHmac]
            public function handle(): JsonResponse
            {
                return new JsonResponse(['success' => true]);
            }
        };

        $event = $this->createEvent($controller, $requestBody, 'api_nfc_users', ['HTTP_X_EXTENSION_AUTH' => $signature]);

        $listener->onKernelController($event);

        $resolvedController = $event->getController();
        self::assertIsCallable($resolvedController);

        $response = $resolvedController();
        self::assertInstanceOf(JsonResponse::class, $response);
        self::assertSame(400, $response->getStatusCode());
        self::assertSame(['success' => false, 'error' => 'Timestamp is outside the allowed window'], json_decode((string) $response->getContent(), true, 512, JSON_THROW_ON_ERROR));
    }

    private function createEvent(object $controller, string $content, string $route, array $server = []): ControllerEvent
    {
        $request = Request::create('/desktop', 'POST', [], [], [], $server, $content);
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
                    default => null,
                };
            }

            public function set(string $name, array|bool|string|int|float|\UnitEnum|null $value): void
            {
            }

            public function has(string $name): bool
            {
                return $name === 'DATA_HASH_SECRET';
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

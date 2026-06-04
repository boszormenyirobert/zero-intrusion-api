<?php

declare(strict_types=1);

namespace App\Tests\Service\CredentialHub\Shared;

use App\Service\Cache\ProcessStateCacheService;
use App\Controller\PayloadValidator\PayloadValidator;
use App\Service\CredentialHub\Shared\SharedRegistrationNewToEncryptService;
use App\Service\Payload\JsonPayloadDecoder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class SharedRegistrationNewToEncryptServiceTest extends TestCase
{
    public function testHandleStoresOnlyPublicKeyInCacheBySessionId(): void
    {
        $request = Request::create('/api/shared/registration/new-to-encrypt', 'POST');

        $payloadValidator = $this->createMock(PayloadValidator::class);
        $payloadValidator
            ->expects(self::once())
            ->method('validatePayload')
            ->with($request, 'shared_registration_new_to_encrypt')
            ->willReturn([
                'shared_registration_new_to_encrypt' => json_encode([
                    'sessionId' => 'session-123',
                    'type' => 'new-user-credential',
                    'publicKey' => 'pub-key-1',
                    'userPublicId' => 'public-1',
                ], JSON_THROW_ON_ERROR),
            ]);

        $cacheService = $this->createMock(ProcessStateCacheService::class);
        $cacheService
            ->expects(self::exactly(2))
            ->method('set')
            ->with(
                self::callback(static fn (string $key): bool => in_array($key, ['session-123', 'session-123_userPublicId'], true)),
                self::callback(static fn (string $value): bool => in_array($value, ['{"publicKey":"pub-key-1"}', 'public-1'], true))
            );

        $service = new SharedRegistrationNewToEncryptService($payloadValidator, $cacheService, new JsonPayloadDecoder());

        self::assertTrue($service->handle($request));
    }

    public function testHandleRejectsInvalidJsonPayload(): void
    {
        $request = Request::create('/api/shared/registration/new-to-encrypt', 'POST');

        $payloadValidator = $this->createMock(PayloadValidator::class);
        $payloadValidator
            ->expects(self::once())
            ->method('validatePayload')
            ->willReturn([
                'shared_registration_new_to_encrypt' => '{invalid-json',
            ]);

        $cacheService = $this->createMock(ProcessStateCacheService::class);
        $cacheService
            ->expects(self::never())
            ->method('set');

        $service = new SharedRegistrationNewToEncryptService($payloadValidator, $cacheService, new JsonPayloadDecoder());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid shared registration new-to-encrypt payload.');

        $service->handle($request);
    }

    public function testHandleAcceptsAlreadyDecodedPayloadArrayAndDefaultsSource(): void
    {
        $request = Request::create('/api/shared/registration/new-to-encrypt', 'POST');

        $payloadValidator = $this->createMock(PayloadValidator::class);
        $payloadValidator
            ->expects(self::once())
            ->method('validatePayload')
            ->with($request, 'shared_registration_new_to_encrypt')
            ->willReturn([
                'shared_registration_new_to_encrypt' => [
                    'sessionId' => 'session-123',
                    'type' => 'new-user-credential',
                    'publicKey' => 'pub-key-2',
                    'userPublicId' => 'public-2',
                ],
            ]);

        $cacheService = $this->createMock(ProcessStateCacheService::class);
        $cacheService
            ->expects(self::exactly(2))
            ->method('set')
            ->with(
                self::callback(static fn (string $key): bool => in_array($key, ['session-123', 'session-123_userPublicId'], true)),
                self::callback(static fn (string $value): bool => in_array($value, ['{"publicKey":"pub-key-2"}', 'public-2'], true))
            );

        $service = new SharedRegistrationNewToEncryptService($payloadValidator, $cacheService, new JsonPayloadDecoder());

        self::assertTrue($service->handle($request));
    }
}

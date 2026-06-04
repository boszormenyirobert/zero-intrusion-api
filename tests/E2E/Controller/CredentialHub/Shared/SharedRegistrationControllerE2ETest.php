<?php

declare(strict_types=1);

namespace App\Tests\E2E\Controller\CredentialHub\Shared;

use App\DTO\QR\CredentialHubIdentityDTO;
use App\EventListener\HmacExtensionValidationListener;
use App\EventListener\HmacMobileValidationListener;
use App\Kernel;
use App\Service\AccessRegistry\AccessRegistryRegistrationService;
use App\Service\AuthBridge\AuthBridgeService;
use App\Service\Cache\ProcessStateCacheService;
use App\Service\CredentialHub\SharedNotificationService;
use App\Service\Crypters\CrypterService;
use App\Service\Hmac\HmacValidator;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SharedRegistrationControllerE2ETest extends WebTestCase
{
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    public function testSharedRegistrationQrIdentityExecutesControllerPipelineToSuccessResponse(): void
    {
        $client = static::createClient();
        $this->mockSuccessfulHmacValidation();

        $identity = $this->createCredentialHubIdentity('registration-process-1', 'mobile-auth-token');

        $authBridgeService = $this->createMock(AuthBridgeService::class);
        $authBridgeService
            ->expects(self::once())
            ->method('generateRequestIdentity')
            ->with('registrationProcessId')
            ->willReturn($identity);
        $authBridgeService
            ->expects(self::once())
            ->method('saveUserCredentialInAuthBridge')
            ->with(
                [
                    'userName' => 'alice',
                    'userPassword' => 'secret-pass',
                    'description' => 'Shared registration request',
                ],
                'registration-process-1'
            )
            ->willReturn(true);
        static::getContainer()->set(AuthBridgeService::class, $authBridgeService);

        $sharedNotificationService = $this->createMock(SharedNotificationService::class);
        $sharedNotificationService
            ->expects(self::once())
            ->method('sendFcmNotification')
            ->with(
                'sharedRegistration',
                'public-1',
                self::callback(static fn (object $qrContent): bool => $qrContent->registrationProcessId === 'registration-process-1')
            );
        static::getContainer()->set(SharedNotificationService::class, $sharedNotificationService);

        $this->requestJson($client, 'POST', '/api/credential-hub/shared/registration/qr-identity', [
            'shared_registration_qr_identity' => [
                'type' => 'registration-domain',
                'source' => 'browser-extension',
                'isNew' => '1',
                'userName' => 'alice',
                'userPassword' => 'secret-pass',
                'description' => 'Shared registration request',
                'userPublicId' => 'public-1',
                'domain' => 'example.com',
                'targetId' => 'target-1',
            ],
        ]);

        self::assertResponseIsSuccessful();

        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertTrue($payload['success']);
        self::assertSame('registration-process-1', $payload['registrationProcessId']);
        self::assertSame('mobile-auth-token', $payload['xExtensionAuthOne']);
        self::assertArrayHasKey('qrCode', $payload);
        self::assertNotSame('', $payload['qrCode']);
    }

    public function testSharedRegistrationNewToEncryptExecutesControllerPipelineToSuccessResponse(): void
    {
        $client = static::createClient();
        $this->mockSuccessfulHmacValidation();
        $this->mockMobileListener();

        $cacheService = $this->createMock(ProcessStateCacheService::class);
        $cacheService
            ->expects(self::exactly(2))
            ->method('set')
            ->with(
                self::callback(static fn (string $key): bool => in_array($key, ['session-2', 'session-2_userPublicId'], true)),
                self::callback(static fn (string $value): bool => in_array($value, ['{"publicKey":"public-key-2"}', 'public-2'], true))
            );
        static::getContainer()->set(ProcessStateCacheService::class, $cacheService);

        $this->requestJson($client, 'POST', '/api/credential-hub/shared/registration/new/to-encrypt', [
            'shared_registration_new_to_encrypt' => [
                'sessionId' => 'session-2',
                'type' => 'new-user-credential',
                'publicKey' => 'public-key-2',
                'userPublicId' => 'public-2',
            ],
        ], [
            'HTTP_X_EXTENSION_AUTH' => 'HMAC extension-token',
        ]);

        self::assertResponseIsSuccessful();
        self::assertSame([
            'success' => true,
            'credentials' => true,
        ], json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR));
    }

    public function testSharedRegistrationNewExecutesControllerPipelineToSuccessResponse(): void
    {
        $client = static::createClient();
        $this->mockSuccessfulHmacValidation();
        $this->mockMobileListener();

        $accessRegistryRegistrationService = $this->createMock(AccessRegistryRegistrationService::class);
        $accessRegistryRegistrationService
            ->expects(self::once())
            ->method('addAccessRegistry')
            ->with(
                self::callback(static fn (array $user): bool => $user['registrationProcessId'] === 'registration-process-3'),
                'domain',
                true
            )
            ->willReturn(['publicId' => 'public-99', 'saved' => true]);
        $accessRegistryRegistrationService
            ->expects(self::once())
            ->method('sendNotification')
            ->with(
                ['publicId' => 'public-99', 'saved' => true],
                self::callback(static fn (array $user): bool => $user['type'] === 'system_hub_registration')
            );
        static::getContainer()->set(AccessRegistryRegistrationService::class, $accessRegistryRegistrationService);

        $this->requestJson($client, 'POST', '/api/credential-hub/shared/registration/new', [
            'shared_registration_new' => [
                'registrationProcessId' => 'registration-process-3',
                'type' => 'system_hub_registration',
                'userPublicId' => 'public-99',
                'domain' => 'example.com',
            ],
        ], [
            'HTTP_X_EXTENSION_AUTH' => 'HMAC extension-token',
        ]);

        self::assertResponseIsSuccessful();
        self::assertSame([
            'registration_process_one' => [
                'publicId' => 'public-99',
                'saved' => true,
            ],
            'error' => '',
        ], json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR));
    }

    public function testSharedRegistrationStateExecutesControllerPipelineToSuccessResponse(): void
    {
        $client = static::createClient();
        $this->mockSuccessfulHmacValidation();
        $this->mockExtensionListener();

        $cacheService = $this->createMock(ProcessStateCacheService::class);
        $cacheService
            ->expects(self::once())
            ->method('get')
            ->with('registration-process-4')
            ->willReturn(json_encode([
                'process_check' => true,
                'registrationState' => 'completed',
            ], JSON_THROW_ON_ERROR));
        static::getContainer()->set(ProcessStateCacheService::class, $cacheService);

        $this->requestJson($client, 'POST', '/api/credential-hub/shared/registration/state', [
            'shared_registration_state' => [
                'processId' => 'registration-process-4',
            ],
        ], [
            'HTTP_X_EXTENSION_AUTH' => 'HMAC extension-token',
        ]);

        self::assertResponseIsSuccessful();

        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertTrue($payload['success']);
        self::assertTrue($payload['process_check']);
        self::assertSame('completed', $payload['registrationState']);
    }

    private function createCredentialHubIdentity(string $registrationProcessId, string $xExtensionAuthOne): CredentialHubIdentityDTO
    {
        $identity = new CredentialHubIdentityDTO();
        $identity->setRegistrationProcessId($registrationProcessId);
        $identity->setXExtensionAuthOne($xExtensionAuthOne);
        $identity->setXExtensionAuthTwo('extension-auth-two');
        $identity->setSecret('secret-value');
        $identity->setIv('iv-value');
        $identity->setCreatedAt('2025-01-01T00:00:00+00:00');
        $identity->setValidCommunication(['mobile', 'extension']);

        return $identity;
    }

    private function mockSuccessfulHmacValidation(): void
    {
        $hmacValidator = $this->createMock(HmacValidator::class);
        $hmacValidator
            ->expects(self::once())
            ->method('validate')
            ->willReturn(true);
        static::getContainer()->set(HmacValidator::class, $hmacValidator);
    }

    private function mockMobileListener(): void
    {
        $mobileListener = $this->createMock(HmacMobileValidationListener::class);
        $mobileListener->expects(self::once())->method('onKernelController');
        static::getContainer()->set(HmacMobileValidationListener::class, $mobileListener);
    }

    private function mockExtensionListener(): void
    {
        $extensionListener = $this->createMock(HmacExtensionValidationListener::class);
        $extensionListener->expects(self::once())->method('onKernelController');
        static::getContainer()->set(HmacExtensionValidationListener::class, $extensionListener);
    }

    private function requestJson(KernelBrowser $client, string $method, string $uri, array $payload, array $server = []): void
    {
        /** @var CrypterService $crypterService */
        $crypterService = static::getContainer()->get(CrypterService::class);
        $crypterService->setData($payload);

        $client->request(
            $method,
            $uri,
            server: array_merge([
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_AUTH' => 'HMAC api-key:signature:1234567890',
            ], $server),
            content: json_encode([
                'iv' => 'outer-iv',
                'zeroIntrusionProyApi' => $crypterService->encryptData(),
            ], JSON_THROW_ON_ERROR),
        );
    }
}
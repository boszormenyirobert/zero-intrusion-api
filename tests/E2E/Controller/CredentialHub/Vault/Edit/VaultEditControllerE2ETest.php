<?php

declare(strict_types=1);

namespace App\Tests\E2E\Controller\CredentialHub\Vault\Edit;

use App\DTO\QR\CredentialHubIdentityDTO;
use App\EventListener\HmacExtensionValidationListener;
use App\EventListener\HmacMobileValidationListener;
use App\Kernel;
use App\Service\AccessRegistry\AccessRegistryVaultService;
use App\Service\AuthBridge\AuthBridgeService;
use App\Service\Cache\ProcessStateCacheService;
use App\Service\CredentialHub\SharedNotificationService;
use App\Service\Crypters\CrypterService;
use App\Service\Hmac\HmacValidator;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class VaultEditControllerE2ETest extends WebTestCase
{
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    public function testVaultEditQrIdentityExecutesControllerPipelineToSuccessResponse(): void
    {
        $client = static::createClient();
        $this->mockSuccessfulHmacValidation();

        $identity = new CredentialHubIdentityDTO();
        $identity->setRegistrationProcessId('registration-process-1');
        $identity->setXExtensionAuthOne('mobile-auth-token');
        $identity->setXExtensionAuthTwo('extension-auth-two');
        $identity->setSecret('secret-value');
        $identity->setIv('iv-value');
        $identity->setCreatedAt('2025-01-01T00:00:00+00:00');
        $identity->setValidCommunication(['mobile', 'extension']);

        $authBridgeService = $this->createMock(AuthBridgeService::class);
        $authBridgeService
            ->expects(self::once())
            ->method('generateRequestIdentity')
            ->with('registrationProcessId')
            ->willReturn($identity);
        $authBridgeService
            ->expects(self::once())
            ->method('saveUserCredentialInAuthBridge')
            ->with([
                'userName' => 'alice',
                'userPassword' => 'secret-pass',
                'description' => 'Edit vault entry',
            ], 'registration-process-1')
            ->willReturn(true);
        static::getContainer()->set(AuthBridgeService::class, $authBridgeService);

        $sharedNotificationService = $this->createMock(SharedNotificationService::class);
        $sharedNotificationService
            ->expects(self::once())
            ->method('sendFcmNotification')
            ->with('vaultEdit', 'public-1', self::callback(static fn (mixed $value): bool => is_object($value)));
        static::getContainer()->set(SharedNotificationService::class, $sharedNotificationService);

        $this->requestJson($client, 'POST', '/api/credential-hub/vault/edit/qr-identity', [
            'vault_edit_qr_identity' => [
                'source' => 'extension',
                'targetId' => 'target-1',
                'type' => 'applications',
                'application' => 'vault-app',
                'userName' => 'alice',
                'userPassword' => 'secret-pass',
                'description' => 'Edit vault entry',
                'userPublicId' => 'public-1',
            ],
        ]);

        self::assertResponseIsSuccessful();

        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($payload['success']);
        self::assertSame('registration-process-1', $payload['registrationProcessId']);
        self::assertNotSame('', $payload['qrCode']);
    }

    public function testVaultEditCredentialExecutesControllerPipelineToSuccessResponse(): void
    {
        $client = static::createClient();
        $this->mockSuccessfulHmacValidation();
        $this->mockMobileListener();

        $accessRegistryVaultService = $this->createMock(AccessRegistryVaultService::class);
        $accessRegistryVaultService
            ->expects(self::once())
            ->method('editApplicationAccessRegistry')
            ->with([
                'registrationProcessId' => 'registration-process-2',
                'targetId' => 'target-2',
            ]);
        static::getContainer()->set(AccessRegistryVaultService::class, $accessRegistryVaultService);

        $this->requestJson($client, 'POST', '/api/credential-hub/vault/edit/credential', [
            'vault_edit_credential' => [
                'registrationProcessId' => 'registration-process-2',
                'targetId' => 'target-2',
            ],
        ], [
            'HTTP_X_EXTENSION_AUTH' => 'HMAC extension-token',
        ]);

        self::assertResponseIsSuccessful();
        self::assertSame([
            'delete_process' => null,
            'error' => '',
        ], json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR));
    }

    public function testVaultEditStateExecutesControllerPipelineToSuccessResponse(): void
    {
        $client = static::createClient();
        $this->mockSuccessfulHmacValidation();
        $this->mockExtensionListener();

        $cacheService = $this->createMock(ProcessStateCacheService::class);
        $cacheService
            ->expects(self::once())
            ->method('get')
            ->with('registration-process-3')
            ->willReturn(json_encode([
                'process' => true,
                'validation' => true,
                'process_check' => true,
                'registrationState' => true,
            ], JSON_THROW_ON_ERROR));
        static::getContainer()->set(ProcessStateCacheService::class, $cacheService);

        $this->requestJson($client, 'POST', '/api/credential-hub/vault/edit/state', [
            'vault_edit_state' => [
                'processId' => 'registration-process-3',
            ],
        ], [
            'HTTP_X_EXTENSION_AUTH' => 'HMAC extension-token',
        ]);

        self::assertResponseIsSuccessful();

        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($payload['success']);
        self::assertTrue($payload['process_check']);
        self::assertTrue($payload['registrationState']);
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
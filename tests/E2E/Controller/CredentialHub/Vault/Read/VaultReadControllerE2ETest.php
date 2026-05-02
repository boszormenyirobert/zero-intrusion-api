<?php

declare(strict_types=1);

namespace App\Tests\E2E\Controller\CredentialHub\Vault\Read;

use App\Controller\CredentialHub\Vault\Read\VaultReadService;
use App\DTO\QR\CredentialHubIdentityDTO;
use App\EventListener\HmacExtensionValidationListener;
use App\EventListener\HmacMobileValidationListener;
use App\Kernel;
use App\Service\AuthBridge\AuthBridgeService;
use App\Service\CredentialHub\SharedNotificationService;
use App\Service\Crypters\CrypterService;
use App\Service\Hmac\HmacValidator;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class VaultReadControllerE2ETest extends WebTestCase
{
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    public function testVaultReadQrIdentityExecutesControllerPipelineToSuccessResponse(): void
    {
        $client = static::createClient();
        $this->mockSuccessfulHmacValidation();

        $identity = new CredentialHubIdentityDTO();
        $identity->setApplicationProcessId('application-process-1');
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
            ->with('applicationProcessId')
            ->willReturn($identity);
        static::getContainer()->set(AuthBridgeService::class, $authBridgeService);

        $sharedNotificationService = $this->createMock(SharedNotificationService::class);
        $sharedNotificationService
            ->expects(self::once())
            ->method('sendFcmNotification')
            ->with(
                'vaultRead',
                'public-1',
                self::callback(static fn (object $qrContent): bool => $qrContent->applicationProcessId === 'application-process-1')
            );
        static::getContainer()->set(SharedNotificationService::class, $sharedNotificationService);

        $this->requestJson($client, 'POST', '/api/credential-hub/vault/read/qr-identity', [
            'vault_read_qr_identity' => [
                'source' => 'extension',
                'type' => 'applications',
                'userPublicId' => 'public-1',
            ],
        ]);

        self::assertResponseIsSuccessful();

        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($payload['success']);
        self::assertSame('application-process-1', $payload['applicationProcessId']);
        self::assertSame('mobile-auth-token', $payload['xExtensionAuthOne']);
        self::assertNotSame('', $payload['qrCode']);
    }

    public function testVaultReadCredentialDecryptedExecutesControllerPipelineToSuccessResponse(): void
    {
        $client = static::createClient();
        $this->mockSuccessfulHmacValidation();
        $this->mockMobileListener();

        $vaultReadService = $this->createMock(VaultReadService::class);
        $vaultReadService
            ->expects(self::once())
            ->method('getDecryptedCredentials')
            ->with('public-2')
            ->willReturn([
                ['application' => 'vault-app', 'targetId' => 'target-2'],
            ]);
        static::getContainer()->set(VaultReadService::class, $vaultReadService);

        $this->requestJson($client, 'POST', '/api/credential-hub/vault/read/credential/decrypted', [
            'vault_read_credential_encrypted' => [
                'publicId' => 'public-2',
            ],
        ], [
            'HTTP_X_EXTENSION_AUTH' => 'HMAC extension-token',
        ]);

        self::assertResponseIsSuccessful();
        self::assertSame([
            'process' => false,
            'validation' => false,
            'process_check' => false,
            'success' => true,
            'credentials' => [
                ['application' => 'vault-app', 'targetId' => 'target-2'],
            ],
        ], json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR));
    }

    public function testVaultReadCredentialExecutesControllerPipelineToSuccessResponse(): void
    {
        $client = static::createClient();
        $this->mockSuccessfulHmacValidation();
        $this->mockMobileListener();

        $authBridgeService = $this->createMock(AuthBridgeService::class);
        $authBridgeService
            ->expects(self::once())
            ->method('persistDecryptedUserData')
            ->with([
                'applicationProcessId' => 'application-process-3',
                'publicId' => 'public-3',
            ])
            ->willReturn(true);
        static::getContainer()->set(AuthBridgeService::class, $authBridgeService);

        $this->requestJson($client, 'POST', '/api/credential-hub/vault/read/credential', [
            'vault_read_credential' => [
                'applicationProcessId' => 'application-process-3',
                'publicId' => 'public-3',
            ],
        ], [
            'HTTP_X_EXTENSION_AUTH' => 'HMAC extension-token',
        ]);

        self::assertResponseIsSuccessful();
        self::assertSame([
            'application_access_process' => true,
            'error' => '',
        ], json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR));
    }

    public function testVaultReadStateExecutesControllerPipelineToSuccessResponse(): void
    {
        $client = static::createClient();
        $this->mockSuccessfulHmacValidation();
        $this->mockExtensionListener();

        $authBridgeService = $this->createMock(AuthBridgeService::class);
        $authBridgeService
            ->expects(self::once())
            ->method('fetchFromAccessTable')
            ->with('application-process-4', 'application')
            ->willReturn([
                'response' => [
                    ['targetId' => 'target-4', 'application' => 'vault-app'],
                ],
                'process' => [
                    'process' => true,
                    'validation' => true,
                    'process_check' => true,
                ],
            ]);
        static::getContainer()->set(AuthBridgeService::class, $authBridgeService);

        $sharedNotificationService = $this->createMock(SharedNotificationService::class);
        $sharedNotificationService
            ->expects(self::once())
            ->method('getUserEmailByTargetId')
            ->willReturn([
                'email' => 'vault@example.com',
                'publicId' => 'public-4',
            ]);
        static::getContainer()->set(SharedNotificationService::class, $sharedNotificationService);

        $this->requestJson($client, 'POST', '/api/credential-hub/vault/read/state', [
            'vault_read_state' => [
                'processId' => 'application-process-4',
            ],
        ], [
            'HTTP_X_EXTENSION_AUTH' => 'HMAC extension-token',
        ]);

        self::assertResponseIsSuccessful();

        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertTrue($payload['success']);
        self::assertTrue($payload['process_check']);
        self::assertSame('vault@example.com', $payload['email']);
        self::assertSame('public-4', $payload['publicId']);
        self::assertSame([
            ['targetId' => 'target-4', 'application' => 'vault-app'],
        ], $payload['applicationList']);
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
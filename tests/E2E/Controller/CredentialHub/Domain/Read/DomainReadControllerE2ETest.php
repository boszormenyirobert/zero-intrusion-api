<?php

declare(strict_types=1);

namespace App\Tests\E2E\Controller\CredentialHub\Domain\Read;

use App\Controller\CredentialHub\Domain\Read\DomainService;
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

final class DomainReadControllerE2ETest extends WebTestCase
{
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    public function testDomainReadQrIdentityExecutesControllerPipelineToSuccessResponse(): void
    {
        $client = static::createClient();
        $this->mockSuccessfulHmacValidation();

        $identity = new CredentialHubIdentityDTO();
        $identity->setDomainProcessId('domain-process-1');
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
            ->with('domainProcessId')
            ->willReturn($identity);
        static::getContainer()->set(AuthBridgeService::class, $authBridgeService);

        $sharedNotificationService = $this->createMock(SharedNotificationService::class);
        $sharedNotificationService
            ->expects(self::once())
            ->method('sendFcmNotification')
            ->with(
                'domainRead',
                'public-1',
                self::callback(static fn (object $qrContent): bool => $qrContent->domainProcessId === 'domain-process-1')
            );
        static::getContainer()->set(SharedNotificationService::class, $sharedNotificationService);

        $this->requestJson($client, 'POST', '/api/credential-hub/domain/read/qr-identity', [
            'domain_read_qr_identity' => [
                'domain' => 'example.com',
                'userPublicId' => 'public-1',
            ],
        ]);

        self::assertResponseIsSuccessful();

        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertTrue($payload['success']);
        self::assertSame('domain-process-1', $payload['domainProcessId']);
        self::assertSame('mobile-auth-token', $payload['xExtensionAuthOne']);
        self::assertArrayHasKey('qrCode', $payload);
        self::assertNotSame('', $payload['qrCode']);
    }

    public function testDomainReadCredentialDecryptedExecutesControllerPipelineToSuccessResponse(): void
    {
        $client = static::createClient();
        $this->mockSuccessfulHmacValidation();
        $this->mockMobileListener();

        $domainService = $this->createMock(DomainService::class);
        $domainService
            ->expects(self::once())
            ->method('getDecryptedCredentials')
            ->with([
                'domainProcessId' => 'domain-process-2',
                'type' => 'domain-login',
                'source' => 'extension',
            ])
            ->willReturn([
                ['credential' => 'alice@example.com', 'domain' => 'example.com'],
            ]);
        static::getContainer()->set(DomainService::class, $domainService);

        $this->requestJson($client, 'POST', '/api/credential-hub/domain/read/credential/decrypted', [
            'domain_read_credential_encrypted' => [
                'domainProcessId' => 'domain-process-2',
                'type' => 'domain-login',
                'source' => 'extension',
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
                ['credential' => 'alice@example.com', 'domain' => 'example.com'],
            ],
        ], json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR));
    }

    public function testDomainReadCredentialExecutesControllerPipelineToSuccessResponse(): void
    {
        $client = static::createClient();
        $this->mockSuccessfulHmacValidation();
        $this->mockMobileListener();

        $domainService = $this->createMock(DomainService::class);
        $domainService
            ->expects(self::once())
            ->method('processCredentialRead')
            ->with([
                'domainProcessId' => 'domain-process-3',
                'type' => 'domain-login',
                'source' => 'extension',
            ])
            ->willReturn(true);
        static::getContainer()->set(DomainService::class, $domainService);

        $this->requestJson($client, 'POST', '/api/credential-hub/domain/read/credential', [
            'domain_read_credential' => [
                'domainProcessId' => 'domain-process-3',
                'type' => 'domain-login',
                'source' => 'extension',
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
            'credentials' => true,
        ], json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR));
    }

    public function testDomainReadStateExecutesControllerPipelineToSuccessResponse(): void
    {
        $client = static::createClient();
        $this->mockSuccessfulHmacValidation();
        $this->mockExtensionListener();

        $authBridgeService = $this->createMock(AuthBridgeService::class);
        $authBridgeService
            ->expects(self::once())
            ->method('fetchFromAccessTable')
            ->with('domain-process-4', 'domain')
            ->willReturn([
                'response' => [
                    ['targetId' => 'target-4', 'domain' => 'example.com'],
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
            ->with([
                'response' => [
                    ['targetId' => 'target-4', 'domain' => 'example.com'],
                ],
                'process' => [
                    'process' => true,
                    'validation' => true,
                    'process_check' => true,
                ],
            ])
            ->willReturn([
                'email' => 'state@example.com',
                'publicId' => 'public-4',
            ]);
        static::getContainer()->set(SharedNotificationService::class, $sharedNotificationService);

        $this->requestJson($client, 'POST', '/api/credential-hub/domain/read/state', [
            'domain_read_state' => [
                'processId' => 'domain-process-4',
            ],
        ], [
            'HTTP_X_EXTENSION_AUTH' => 'HMAC extension-token',
        ]);

        self::assertResponseIsSuccessful();

        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertTrue($payload['success']);
        self::assertTrue($payload['process_check']);
        self::assertSame('state@example.com', $payload['email']);
        self::assertSame('public-4', $payload['publicId']);
        self::assertSame([
            ['targetId' => 'target-4', 'domain' => 'example.com'],
        ], $payload['domainList']);
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
<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller\CredentialHub\Vault\Read;

use App\Controller\PayloadValidator\PayloadValidator;
use App\DTO\CredentialHub\Vault\Read\VaultReadQrIdentityRequestDTO;
use App\EventListener\HmacExtensionValidationListener;
use App\EventListener\HmacMobileValidationListener;
use App\Kernel;
use App\Service\CredentialHub\Vault\Read\VaultReadCredentialDecryptedService;
use App\Service\CredentialHub\Vault\Read\VaultReadQrIdentityRequestMapper;
use App\Service\CredentialHub\Vault\Read\VaultReadQrIdentityService;
use App\Service\Hmac\HmacValidator;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class VaultReadControllerAdditionalIntegrationTest extends WebTestCase
{
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    public function testVaultReadQrIdentityReturnsSuccessPayload(): void
    {
        $client = static::createClient();

        $payloadValidator = $this->createMock(PayloadValidator::class);
        $payloadValidator
            ->expects(self::once())
            ->method('validatePayload')
            ->willReturn(['vault_read_qr_identity' => ['userPublicId' => 'public-1']]);
        static::getContainer()->set(PayloadValidator::class, $payloadValidator);

        $requestMapper = $this->createMock(VaultReadQrIdentityRequestMapper::class);
        $requestMapper
            ->expects(self::once())
            ->method('map')
            ->willReturn(new VaultReadQrIdentityRequestDTO('extension', 'vault-read', 'public-1', ['userPublicId' => 'public-1']));
        static::getContainer()->set(VaultReadQrIdentityRequestMapper::class, $requestMapper);

        $service = $this->createMock(VaultReadQrIdentityService::class);
        $service
            ->expects(self::once())
            ->method('handle')
            ->willReturn(['applicationProcessId' => 'process-1']);
        static::getContainer()->set(VaultReadQrIdentityService::class, $service);

        $extensionListener = $this->createMock(HmacExtensionValidationListener::class);
        $extensionListener->expects(self::once())->method('onKernelController');
        static::getContainer()->set(HmacExtensionValidationListener::class, $extensionListener);

        $hmacValidator = $this->createMock(HmacValidator::class);
        $hmacValidator->expects(self::once())->method('validate')->willReturn(true);
        static::getContainer()->set(HmacValidator::class, $hmacValidator);

        $client->request(
            'POST',
            '/api/credential-hub/vault/read/qr-identity',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_AUTH' => 'HMAC client:signature:123',
                'HTTP_X_EXTENSION_AUTH' => 'HMAC extension',
            ],
            content: json_encode(['iv' => 'iv', 'zeroIntrusionProyApi' => 'payload'], JSON_THROW_ON_ERROR),
        );

        self::assertResponseIsSuccessful();
        self::assertSame([
            'process' => false,
            'validation' => false,
            'process_check' => false,
            'success' => true,
            'applicationProcessId' => 'process-1',
        ], json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR));
    }

    public function testVaultReadCredentialDecryptedReturnsSuccessPayload(): void
    {
        $client = static::createClient();

        $service = $this->createMock(VaultReadCredentialDecryptedService::class);
        $service
            ->expects(self::once())
            ->method('handle')
            ->willReturn(['credentials' => [['application' => 'vault-app']]]);
        static::getContainer()->set(VaultReadCredentialDecryptedService::class, $service);

        $mobileListener = $this->createMock(HmacMobileValidationListener::class);
        $mobileListener->expects(self::once())->method('onKernelController');
        static::getContainer()->set(HmacMobileValidationListener::class, $mobileListener);

        $hmacValidator = $this->createMock(HmacValidator::class);
        $hmacValidator->expects(self::once())->method('validate')->willReturn(true);
        static::getContainer()->set(HmacValidator::class, $hmacValidator);

        $client->request(
            'POST',
            '/api/credential-hub/vault/read/credential/decrypted',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_AUTH' => 'HMAC client:signature:123',
                'HTTP_X_EXTENSION_AUTH' => 'HMAC extension',
            ],
            content: json_encode(['iv' => 'iv', 'zeroIntrusionProyApi' => 'payload'], JSON_THROW_ON_ERROR),
        );

        self::assertResponseIsSuccessful();
        self::assertSame([
            'process' => false,
            'validation' => false,
            'process_check' => false,
            'success' => true,
            'credentials' => [['application' => 'vault-app']],
        ], json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR));
    }
}
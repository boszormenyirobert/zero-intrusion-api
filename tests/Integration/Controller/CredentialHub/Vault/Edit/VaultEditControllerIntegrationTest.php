<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller\CredentialHub\Vault\Edit;

use App\Controller\PayloadValidator\PayloadValidator;
use App\DTO\CredentialHub\Vault\Edit\VaultEditCredentialResultDTO;
use App\DTO\CredentialHub\Vault\Edit\VaultEditQrIdentityRequestDTO;
use App\EventListener\HmacExtensionValidationListener;
use App\EventListener\HmacMobileValidationListener;
use App\Kernel;
use App\Service\CredentialHub\Vault\Edit\VaultEditCredentialService;
use App\Service\CredentialHub\Vault\Edit\VaultEditQrIdentityRequestMapper;
use App\Service\CredentialHub\Vault\Edit\VaultEditQrIdentityService;
use App\Service\CredentialHub\Vault\Edit\VaultEditStateService;
use App\Service\Hmac\HmacValidator;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class VaultEditControllerIntegrationTest extends WebTestCase
{
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    public function testVaultEditQrIdentityReturnsSuccessPayload(): void
    {
        $client = static::createClient();

        $payloadValidator = $this->createMock(PayloadValidator::class);
        $payloadValidator
            ->expects(self::once())
            ->method('validatePayload')
            ->willReturn(['vault_edit_qr_identity' => ['userPublicId' => 'public-1']]);
        static::getContainer()->set(PayloadValidator::class, $payloadValidator);

        $requestMapper = $this->createMock(VaultEditQrIdentityRequestMapper::class);
        $requestMapper
            ->expects(self::once())
            ->method('map')
            ->willReturn(new VaultEditQrIdentityRequestDTO('public-1', ['userPublicId' => 'public-1']));
        static::getContainer()->set(VaultEditQrIdentityRequestMapper::class, $requestMapper);

        $service = $this->createMock(VaultEditQrIdentityService::class);
        $service
            ->expects(self::once())
            ->method('handle')
            ->willReturn(['registrationProcessId' => 'process-1']);
        static::getContainer()->set(VaultEditQrIdentityService::class, $service);

        $extensionListener = $this->createMock(HmacExtensionValidationListener::class);
        $extensionListener->expects(self::once())->method('onKernelController');
        static::getContainer()->set(HmacExtensionValidationListener::class, $extensionListener);

        $hmacValidator = $this->createMock(HmacValidator::class);
        $hmacValidator->expects(self::once())->method('validate')->willReturn(true);
        static::getContainer()->set(HmacValidator::class, $hmacValidator);

        $client->request(
            'POST',
            '/api/credential-hub/vault/edit/qr-identity',
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
            'registrationProcessId' => 'process-1',
        ], json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR));
    }

    public function testVaultEditCredentialReturnsRawPayload(): void
    {
        $client = static::createClient();

        $service = $this->createMock(VaultEditCredentialService::class);
        $service
            ->expects(self::once())
            ->method('handle')
            ->willReturn(new VaultEditCredentialResultDTO(true, ''));
        static::getContainer()->set(VaultEditCredentialService::class, $service);

        $mobileListener = $this->createMock(HmacMobileValidationListener::class);
        $mobileListener->expects(self::once())->method('onKernelController');
        static::getContainer()->set(HmacMobileValidationListener::class, $mobileListener);

        $hmacValidator = $this->createMock(HmacValidator::class);
        $hmacValidator->expects(self::once())->method('validate')->willReturn(true);
        static::getContainer()->set(HmacValidator::class, $hmacValidator);

        $client->request(
            'POST',
            '/api/credential-hub/vault/edit/credential',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_AUTH' => 'HMAC client:signature:123',
                'HTTP_X_EXTENSION_AUTH' => 'HMAC extension',
            ],
            content: json_encode(['iv' => 'iv', 'zeroIntrusionProyApi' => 'payload'], JSON_THROW_ON_ERROR),
        );

        self::assertResponseIsSuccessful();
        self::assertSame([
            'delete_process' => true,
            'error' => '',
        ], json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR));
    }

    public function testVaultEditStateReturnsErrorPayloadWhenProcessIdIsMissing(): void
    {
        $client = static::createClient();

        $service = $this->createMock(VaultEditStateService::class);
        $service->expects(self::once())->method('handle')->willReturn(null);
        static::getContainer()->set(VaultEditStateService::class, $service);

        $extensionListener = $this->createMock(HmacExtensionValidationListener::class);
        $extensionListener->expects(self::once())->method('onKernelController');
        static::getContainer()->set(HmacExtensionValidationListener::class, $extensionListener);

        $hmacValidator = $this->createMock(HmacValidator::class);
        $hmacValidator->expects(self::once())->method('validate')->willReturn(true);
        static::getContainer()->set(HmacValidator::class, $hmacValidator);

        $client->request(
            'POST',
            '/api/credential-hub/vault/edit/state',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_AUTH' => 'HMAC client:signature:123',
                'HTTP_X_EXTENSION_AUTH' => 'HMAC extension',
            ],
            content: json_encode(['iv' => 'iv', 'zeroIntrusionProyApi' => 'payload'], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(400);
        self::assertSame([
            'success' => false,
            'error' => 'Invalid or missing processId',
        ], json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR));
    }
}
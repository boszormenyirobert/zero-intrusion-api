<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller\CredentialHub\Vault\Read;

use App\DTO\CredentialHub\Vault\Read\VaultReadCredentialResultDTO;
use App\EventListener\HmacExtensionValidationListener;
use App\EventListener\HmacMobileValidationListener;
use App\Kernel;
use App\Service\CredentialHub\Vault\Read\VaultReadCredentialService;
use App\Service\CredentialHub\Vault\Read\VaultReadStateService;
use App\Service\Hmac\HmacValidator;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class VaultReadControllerIntegrationTest extends WebTestCase
{
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    public function testVaultReadStateReturnsSuccessPayload(): void
    {
        $client = static::createClient();

        $stateService = $this->createMock(VaultReadStateService::class);
        $stateService
            ->expects(self::once())
            ->method('handle')
            ->willReturn(['process_check' => true, 'applicationList' => []]);
        static::getContainer()->set(VaultReadStateService::class, $stateService);

        $extensionListener = $this->createMock(HmacExtensionValidationListener::class);
        $extensionListener->expects(self::once())->method('onKernelController');
        static::getContainer()->set(HmacExtensionValidationListener::class, $extensionListener);

        $hmacValidator = $this->createMock(HmacValidator::class);
        $hmacValidator->expects(self::once())->method('validate')->willReturn(true);
        static::getContainer()->set(HmacValidator::class, $hmacValidator);

        $client->request(
            'POST',
            '/api/credential-hub/vault/read/state',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_AUTH' => 'HMAC client:signature:123',
                'HTTP_X_EXTENSION_AUTH' => 'HMAC extension',
            ],
            content: json_encode([
                'iv' => 'iv-value',
                'zeroIntrusionProyApi' => 'encrypted-payload',
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseIsSuccessful();
        self::assertSame([
            'process' => false,
            'validation' => false,
            'process_check' => true,
            'success' => true,
            'applicationList' => [],
        ], json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR));
    }

    public function testVaultReadCredentialReturnsRawProcessPayload(): void
    {
        $client = static::createClient();

        $credentialService = $this->createMock(VaultReadCredentialService::class);
        $credentialService
            ->expects(self::once())
            ->method('handle')
            ->willReturn(new VaultReadCredentialResultDTO(true, ''));
        static::getContainer()->set(VaultReadCredentialService::class, $credentialService);

        $hmacValidator = $this->createMock(HmacValidator::class);
        $hmacValidator->expects(self::once())->method('validate')->willReturn(true);
        static::getContainer()->set(HmacValidator::class, $hmacValidator);

        $mobileListener = $this->createMock(HmacMobileValidationListener::class);
        $mobileListener->expects(self::once())->method('onKernelController');
        static::getContainer()->set(HmacMobileValidationListener::class, $mobileListener);

        $client->request(
            'POST',
            '/api/credential-hub/vault/read/credential',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_AUTH' => 'HMAC client:signature:123',
                'HTTP_X_EXTENSION_AUTH' => 'HMAC extension',
            ],
            content: json_encode([
                'iv' => 'iv-value',
                'zeroIntrusionProyApi' => 'encrypted-payload',
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseIsSuccessful();
        self::assertSame([
            'application_access_process' => true,
            'error' => '',
        ], json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR));
    }
}
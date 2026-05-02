<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller\CredentialHub\Vault\Delete;

use App\EventListener\HmacExtensionValidationListener;
use App\Kernel;
use App\Service\CredentialHub\Vault\Delete\VaultDeleteStateService;
use App\Service\Hmac\HmacValidator;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class VaultDeleteControllerIntegrationTest extends WebTestCase
{
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    public function testVaultDeleteStateReturnsSuccessPayload(): void
    {
        $client = static::createClient();

        $stateService = $this->createMock(VaultDeleteStateService::class);
        $stateService
            ->expects(self::once())
            ->method('handle')
            ->willReturn(['process_check' => true]);
        static::getContainer()->set(VaultDeleteStateService::class, $stateService);

        $extensionListener = $this->createMock(HmacExtensionValidationListener::class);
        $extensionListener->expects(self::once())->method('onKernelController');
        static::getContainer()->set(HmacExtensionValidationListener::class, $extensionListener);

        $hmacValidator = $this->createMock(HmacValidator::class);
        $hmacValidator->expects(self::once())->method('validate')->willReturn(true);
        static::getContainer()->set(HmacValidator::class, $hmacValidator);

        $client->request(
            'POST',
            '/api/credential-hub/vault/delete/state',
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
        ], json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR));
    }
}
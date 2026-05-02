<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller\CredentialHub\Domain\Read;

use App\EventListener\HmacExtensionValidationListener;
use App\EventListener\HmacMobileValidationListener;
use App\Kernel;
use App\Service\CredentialHub\Domain\Read\DomainReadCredentialDecryptedService;
use App\Service\CredentialHub\Domain\Read\DomainReadCredentialService;
use App\Service\Hmac\HmacValidator;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class DomainReadControllerIntegrationTest extends WebTestCase
{
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    public function testDomainReadCredentialDecryptedReturnsSuccessPayload(): void
    {
        $client = static::createClient();

        $service = $this->createMock(DomainReadCredentialDecryptedService::class);
        $service
            ->expects(self::once())
            ->method('handle')
            ->willReturn(['credentials' => [['credential' => 'secret']]]);
        static::getContainer()->set(DomainReadCredentialDecryptedService::class, $service);

        $hmacValidator = $this->createMock(HmacValidator::class);
        $hmacValidator->expects(self::once())->method('validate')->willReturn(true);
        static::getContainer()->set(HmacValidator::class, $hmacValidator);

        $mobileListener = $this->createMock(HmacMobileValidationListener::class);
        $mobileListener->expects(self::once())->method('onKernelController');
        static::getContainer()->set(HmacMobileValidationListener::class, $mobileListener);

        $client->request(
            'POST',
            '/api/credential-hub/domain/read/credential/decrypted',
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
            'credentials' => [['credential' => 'secret']],
        ], json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR));
    }

    public function testDomainReadCredentialReturnsSuccessPayload(): void
    {
        $client = static::createClient();

        $service = $this->createMock(DomainReadCredentialService::class);
        $service
            ->expects(self::once())
            ->method('handle')
            ->willReturn(['credentials' => true]);
        static::getContainer()->set(DomainReadCredentialService::class, $service);

        $hmacValidator = $this->createMock(HmacValidator::class);
        $hmacValidator->expects(self::once())->method('validate')->willReturn(true);
        static::getContainer()->set(HmacValidator::class, $hmacValidator);

        $mobileListener = $this->createMock(HmacMobileValidationListener::class);
        $mobileListener->expects(self::once())->method('onKernelController');
        static::getContainer()->set(HmacMobileValidationListener::class, $mobileListener);

        $client->request(
            'POST',
            '/api/credential-hub/domain/read/credential',
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
            'credentials' => true,
        ], json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR));
    }
}
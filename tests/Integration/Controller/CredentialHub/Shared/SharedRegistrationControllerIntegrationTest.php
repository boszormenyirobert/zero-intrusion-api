<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller\CredentialHub\Shared;

use App\EventListener\HmacExtensionValidationListener;
use App\EventListener\HmacMobileValidationListener;
use App\Kernel;
use App\DTO\CredentialHub\Shared\SharedRegistrationNewResultDTO;
use App\DTO\CredentialHub\Shared\SharedRegistrationNewToEncryptResultDTO;
use App\Service\CredentialHub\Shared\SharedRegistrationNewService;
use App\Service\CredentialHub\Shared\SharedRegistrationNewToEncryptService;
use App\Service\CredentialHub\Shared\SharedRegistrationStateService;
use App\Service\Hmac\HmacValidator;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SharedRegistrationControllerIntegrationTest extends WebTestCase
{
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    public function testSharedRegistrationStateReturnsSuccessPayload(): void
    {
        $client = static::createClient();

        $stateService = $this->createMock(SharedRegistrationStateService::class);
        $stateService
            ->expects(self::once())
            ->method('handle')
            ->willReturn(['process_check' => true]);
        static::getContainer()->set(SharedRegistrationStateService::class, $stateService);

        $extensionListener = $this->createMock(HmacExtensionValidationListener::class);
        $extensionListener->expects(self::once())->method('onKernelController');
        static::getContainer()->set(HmacExtensionValidationListener::class, $extensionListener);

        $hmacValidator = $this->createMock(HmacValidator::class);
        $hmacValidator->expects(self::once())->method('validate')->willReturn(true);
        static::getContainer()->set(HmacValidator::class, $hmacValidator);

        $client->request(
            'POST',
            '/api/credential-hub/shared/registration/state',
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

    public function testSharedRegistrationNewToEncryptReturnsRawPayload(): void
    {
        $client = static::createClient();

        $service = $this->createMock(SharedRegistrationNewToEncryptService::class);
        $service
            ->expects(self::once())
            ->method('handle')
            ->willReturn(new SharedRegistrationNewToEncryptResultDTO(['credential' => 'secret'], ''));
        static::getContainer()->set(SharedRegistrationNewToEncryptService::class, $service);

        $mobileListener = $this->createMock(HmacMobileValidationListener::class);
        $mobileListener->expects(self::once())->method('onKernelController');
        static::getContainer()->set(HmacMobileValidationListener::class, $mobileListener);

        $hmacValidator = $this->createMock(HmacValidator::class);
        $hmacValidator->expects(self::once())->method('validate')->willReturn(true);
        static::getContainer()->set(HmacValidator::class, $hmacValidator);

        $client->request(
            'POST',
            '/api/credential-hub/shared/registration/new/to-encrypt',
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
            'registration_process_init' => ['credential' => 'secret'],
            'error' => '',
        ], json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR));
    }

    public function testSharedRegistrationNewReturnsRawPayload(): void
    {
        $client = static::createClient();

        $service = $this->createMock(SharedRegistrationNewService::class);
        $service
            ->expects(self::once())
            ->method('handle')
            ->willReturn(new SharedRegistrationNewResultDTO(['id' => 10], ''));
        static::getContainer()->set(SharedRegistrationNewService::class, $service);

        $mobileListener = $this->createMock(HmacMobileValidationListener::class);
        $mobileListener->expects(self::once())->method('onKernelController');
        static::getContainer()->set(HmacMobileValidationListener::class, $mobileListener);

        $hmacValidator = $this->createMock(HmacValidator::class);
        $hmacValidator->expects(self::once())->method('validate')->willReturn(true);
        static::getContainer()->set(HmacValidator::class, $hmacValidator);

        $client->request(
            'POST',
            '/api/credential-hub/shared/registration/new',
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
            'registration_process_one' => ['id' => 10],
            'error' => '',
        ], json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR));
    }
}
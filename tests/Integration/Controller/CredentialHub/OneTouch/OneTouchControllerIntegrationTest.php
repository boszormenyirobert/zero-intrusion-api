<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller\CredentialHub\OneTouch;

use App\DTO\CredentialHub\OneTouch\OneTouchIdentifierResultDTO;
use App\EventListener\HmacExtensionValidationListener;
use App\EventListener\HmacMobileValidationListener;
use App\Kernel;
use App\Service\CredentialHub\OneTouch\OneTouchIdentifierService;
use App\Service\CredentialHub\OneTouch\OneTouchStateService;
use App\Service\Hmac\HmacValidator;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class OneTouchControllerIntegrationTest extends WebTestCase
{
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    public function testOneTouchIdentifierReturnsRawProcessPayload(): void
    {
        $client = static::createClient();

        $identifierService = $this->createMock(OneTouchIdentifierService::class);
        $identifierService
            ->expects(self::once())
            ->method('handle')
            ->willReturn(new OneTouchIdentifierResultDTO(true, ''));
        static::getContainer()->set(OneTouchIdentifierService::class, $identifierService);

        $hmacValidator = $this->createMock(HmacValidator::class);
        $hmacValidator->expects(self::once())->method('validate')->willReturn(true);
        static::getContainer()->set(HmacValidator::class, $hmacValidator);

        $mobileListener = $this->createMock(HmacMobileValidationListener::class);
        $mobileListener->expects(self::once())->method('onKernelController');
        static::getContainer()->set(HmacMobileValidationListener::class, $mobileListener);

        $client->request(
            'POST',
            '/api/credential-hub/one-touch/identifier',
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
            'one_touch_process' => true,
            'error' => '',
        ], json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR));
    }

    public function testOneTouchStateReturnsEnvelopedSuccessPayload(): void
    {
        $client = static::createClient();

        $stateService = $this->createMock(OneTouchStateService::class);
        $stateService
            ->expects(self::once())
            ->method('handle')
            ->willReturn(['login_process_check' => true]);
        static::getContainer()->set(OneTouchStateService::class, $stateService);

        $extensionListener = $this->createMock(HmacExtensionValidationListener::class);
        $extensionListener->expects(self::once())->method('onKernelController');
        static::getContainer()->set(HmacExtensionValidationListener::class, $extensionListener);

        $hmacValidator = $this->createMock(HmacValidator::class);
        $hmacValidator->expects(self::once())->method('validate')->willReturn(true);
        static::getContainer()->set(HmacValidator::class, $hmacValidator);

        $client->request(
            'POST',
            '/api/credential-hub/one-touch/state',
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
            'process_check' => false,
            'success' => true,
            'login_process_check' => true,
        ], json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR));
    }
}
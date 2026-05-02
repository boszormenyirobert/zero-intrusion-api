<?php

declare(strict_types=1);

namespace App\Tests\E2E\Controller\CredentialHub\OneTouch;

use App\DTO\QR\CredentialHubIdentityDTO;
use App\Entity\AuthBridge;
use App\EventListener\HmacExtensionValidationListener;
use App\EventListener\HmacMobileValidationListener;
use App\Kernel;
use App\Service\AuthBridge\AuthBridgeService;
use App\Service\Crypters\CrypterService;
use App\Service\Hmac\HmacValidator;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class OneTouchControllerE2ETest extends WebTestCase
{
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    public function testOneTouchQrIdentityExecutesControllerPipelineToSuccessResponse(): void
    {
        $client = static::createClient();
        $this->mockSuccessfulHmacValidation();

        $identity = new CredentialHubIdentityDTO();
        $identity->setOneTouchProcessId('one-touch-process-1');
        $identity->setXExtensionAuthOne('mobile-auth-token');
        $identity->setXExtensionAuthTwo('extension-auth-two');
        $identity->setSecret('secret-value');
        $identity->setIv('iv-value');
        $identity->setCreatedAt('2025-01-01T00:00:00+00:00');
        $identity->setValidCommunication(['mobile']);

        $authBridgeService = $this->createMock(AuthBridgeService::class);
        $authBridgeService
            ->expects(self::once())
            ->method('generateRequestIdentity')
            ->with('oneTouchProcessId')
            ->willReturn($identity);
        static::getContainer()->set(AuthBridgeService::class, $authBridgeService);

        $this->requestJson($client, 'POST', '/api/credential-hub/one-touch/qr-identity', [
            'one_touch_qr_identity' => [
                'type' => 'one-touch-login',
                'source' => 'browser-extension',
            ],
        ]);

        self::assertResponseIsSuccessful();

        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertTrue($payload['success']);
        self::assertSame('one-touch-process-1', $payload['oneTouchProcessId']);
        self::assertSame('mobile-auth-token', $payload['xExtensionAuthOne']);
        self::assertArrayHasKey('qrCode', $payload);
        self::assertNotSame('', $payload['qrCode']);
    }

    public function testOneTouchIdentifierExecutesControllerPipelineToSuccessResponse(): void
    {
        $client = static::createClient();
        $this->mockSuccessfulHmacValidation();
        $this->mockMobileListener();

        $authBridgeService = $this->createMock(AuthBridgeService::class);
        $authBridgeService
            ->expects(self::once())
            ->method('persistDecryptedUserData')
            ->with([
                'oneTouchProcessId' => 'one-touch-process-2',
                'publicId' => 'public-2',
                'email' => 'user@example.com',
            ])
            ->willReturn(true);
        static::getContainer()->set(AuthBridgeService::class, $authBridgeService);

        $this->requestJson($client, 'POST', '/api/credential-hub/one-touch/identifier', [
            'one_touch_identifier' => [
                'oneTouchProcessId' => 'one-touch-process-2',
                'publicId' => 'public-2',
                'email' => 'user@example.com',
            ],
        ], [
            'HTTP_X_EXTENSION_AUTH' => 'HMAC extension-token',
        ]);

        self::assertResponseIsSuccessful();
        self::assertSame([
            'one_touch_process' => true,
            'error' => '',
        ], json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR));
    }

    public function testOneTouchStateExecutesControllerPipelineToSuccessResponse(): void
    {
        $client = static::createClient();
        $this->mockSuccessfulHmacValidation();
        $this->mockExtensionListener();

        $authBridge = new AuthBridge();
        $authBridge->setOneTouchProcessId('one-touch-process-3');
        $authBridge->setUserIdentity(json_encode([
            'email' => 'state@example.com',
            'publicId' => 'public-3',
        ], JSON_THROW_ON_ERROR));

        $authBridgeService = $this->createMock(AuthBridgeService::class);
        $authBridgeService
            ->expects(self::once())
            ->method('fetchForOneTouch')
            ->with('one-touch-process-3', 'oneTouchProcessId')
            ->willReturn($authBridge);
        static::getContainer()->set(AuthBridgeService::class, $authBridgeService);

        $this->requestJson($client, 'POST', '/api/credential-hub/one-touch/state', [
            'one_touch_state' => [
                'processId' => 'one-touch-process-3',
            ],
        ], [
            'HTTP_X_EXTENSION_AUTH' => 'HMAC extension-token',
        ]);

        self::assertResponseIsSuccessful();

        $payload = json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);

        self::assertTrue($payload['success']);
        self::assertSame('state@example.com', $payload['email']);
        self::assertSame('public-3', $payload['publicId']);
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
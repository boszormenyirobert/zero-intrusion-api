<?php

declare(strict_types=1);

namespace App\Tests\Integration\EventListener;

use App\Kernel;
use App\Service\Crypters\CrypterService;
use App\Service\Hmac\HmacValidator;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SpecificHmacListenerJsonBoundaryIntegrationTest extends WebTestCase
{
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    public function testExtensionHmacRouteRejectsInvalidInnerPayloadWithUniformPayload(): void
    {
        $client = static::createClient();

        $validator = $this->createMock(HmacValidator::class);
        $validator->expects(self::once())->method('validate')->willReturn(true);
        static::getContainer()->set(HmacValidator::class, $validator);

        $client->request(
            'POST',
            '/api/credential-hub/domain/read/state',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_AUTH' => 'HMAC client:signature:123',
                'HTTP_X_EXTENSION_AUTH' => 'HMAC extension',
            ],
            content: json_encode([
                'iv' => 'iv-value',
                'zeroIntrusionProyApi' => $this->encryptPayload([
                    'domain_read_state' => 'not-an-array',
                ]),
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(400);
        self::assertSame([
            'success' => false,
            'error' => 'Invalid inner payload',
        ], json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR));
    }

    public function testMobileHmacRouteRejectsMissingRoutePayloadWithUniformPayload(): void
    {
        $client = static::createClient();

        $validator = $this->createMock(HmacValidator::class);
        $validator->expects(self::once())->method('validate')->willReturn(true);
        static::getContainer()->set(HmacValidator::class, $validator);

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
                'zeroIntrusionProyApi' => $this->encryptPayload([
                    'other_route' => ['registrationProcessId' => 'process-1'],
                ]),
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(400);
        self::assertSame([
            'success' => false,
            'error' => 'payloadKey missing or null',
        ], json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR));
    }

    public function testDesktopHmacRouteRejectsInvalidInnerPayloadWithUniformPayload(): void
    {
        $client = static::createClient();

        $validator = $this->createMock(HmacValidator::class);
        $validator->expects(self::once())->method('validate')->willReturn(true);
        static::getContainer()->set(HmacValidator::class, $validator);

        $client->request(
            'POST',
            '/api/nfc/users',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_AUTH' => 'HMAC client:signature:123',
                'HTTP_X_EXTENSION_AUTH' => 'desktop-signature',
            ],
            content: json_encode([
                'iv' => 'iv-value',
                'zeroIntrusionProyApi' => $this->encryptPayload([
                    'api_nfc_users' => 'not-an-array',
                ]),
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(400);
        self::assertSame([
            'success' => false,
            'error' => 'Invalid inner payload',
        ], json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR));
    }

    private function encryptPayload(array $payload): string
    {
        $crypter = static::getContainer()->get(CrypterService::class);
        $crypter->setData($payload);

        return $crypter->encryptData();
    }
}
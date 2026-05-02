<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller\CredentialHub\Domain\Read;

use App\EventListener\HmacExtensionValidationListener;
use App\Kernel;
use App\Service\CredentialHub\Domain\Read\DomainReadStateService;
use App\Service\Hmac\HmacValidator;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class DomainReadStateControllerIntegrationTest extends WebTestCase
{
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    public function testDomainReadStateReturnsErrorPayloadWhenProcessIdIsMissing(): void
    {
        $client = static::createClient();

        $service = $this->createMock(DomainReadStateService::class);
        $service->expects(self::once())->method('handle')->willReturn(null);
        static::getContainer()->set(DomainReadStateService::class, $service);

        $extensionListener = $this->createMock(HmacExtensionValidationListener::class);
        $extensionListener->expects(self::once())->method('onKernelController');
        static::getContainer()->set(HmacExtensionValidationListener::class, $extensionListener);

        $hmacValidator = $this->createMock(HmacValidator::class);
        $hmacValidator->expects(self::once())->method('validate')->willReturn(true);
        static::getContainer()->set(HmacValidator::class, $hmacValidator);

        $client->request(
            'POST',
            '/api/credential-hub/domain/read/state',
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
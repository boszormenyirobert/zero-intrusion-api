<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller\CredentialHub\Domain\Delete;

use App\Controller\PayloadValidator\PayloadValidator;
use App\DTO\CredentialHub\Domain\Delete\DomainDeleteQrIdentityRequestDTO;
use App\EventListener\HmacExtensionValidationListener;
use App\Kernel;
use App\Service\CredentialHub\Domain\Delete\DomainDeleteQrIdentityRequestMapper;
use App\Service\CredentialHub\Domain\Delete\DomainDeleteQrIdentityService;
use App\Service\Hmac\HmacValidator;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class DomainDeleteQrIdentityControllerIntegrationTest extends WebTestCase
{
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    public function testDomainDeleteQrIdentityReturnsSuccessPayload(): void
    {
        $client = static::createClient();

        $payloadValidator = $this->createMock(PayloadValidator::class);
        $payloadValidator
            ->expects(self::once())
            ->method('validatePayload')
            ->willReturn(['domain_delete_qr_identity' => '{"domain":"example.test"}']);
        static::getContainer()->set(PayloadValidator::class, $payloadValidator);

        $requestMapper = $this->createMock(DomainDeleteQrIdentityRequestMapper::class);
        $requestMapper
            ->expects(self::once())
            ->method('map')
            ->willReturn(new DomainDeleteQrIdentityRequestDTO('example.test', 'domain-delete', 'extension', 'target-1', 'public-1'));
        static::getContainer()->set(DomainDeleteQrIdentityRequestMapper::class, $requestMapper);

        $service = $this->createMock(DomainDeleteQrIdentityService::class);
        $service
            ->expects(self::once())
            ->method('handle')
            ->willReturn(['removeProcessId' => 'remove-1']);
        static::getContainer()->set(DomainDeleteQrIdentityService::class, $service);

        $extensionListener = $this->createMock(HmacExtensionValidationListener::class);
        $extensionListener->expects(self::once())->method('onKernelController');
        static::getContainer()->set(HmacExtensionValidationListener::class, $extensionListener);

        $hmacValidator = $this->createMock(HmacValidator::class);
        $hmacValidator->expects(self::once())->method('validate')->willReturn(true);
        static::getContainer()->set(HmacValidator::class, $hmacValidator);

        $client->request(
            'POST',
            '/api/credential-hub/domain/delete/qr-identity',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_AUTH' => 'HMAC client:signature:123',
                'HTTP_X_EXTENSION_AUTH' => 'HMAC extension',
            ],
            content: json_encode(['iv' => 'iv-value', 'zeroIntrusionProyApi' => 'encrypted-payload'], JSON_THROW_ON_ERROR),
        );

        self::assertResponseIsSuccessful();
        self::assertSame([
            'process' => false,
            'validation' => false,
            'process_check' => false,
            'success' => true,
            'removeProcessId' => 'remove-1',
        ], json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR));
    }
}
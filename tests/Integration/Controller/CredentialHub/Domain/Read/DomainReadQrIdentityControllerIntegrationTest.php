<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller\CredentialHub\Domain\Read;

use App\Controller\PayloadValidator\PayloadValidator;
use App\DTO\CredentialHub\Domain\Read\ExtensionCredentialRequestDTO;
use App\EventListener\HmacExtensionValidationListener;
use App\Kernel;
use App\Service\CredentialHub\Domain\Read\DomainReadQrIdentityRequestMapper;
use App\Service\CredentialHub\Domain\Read\DomainReadQrIdentityService;
use App\Service\Hmac\HmacValidator;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class DomainReadQrIdentityControllerIntegrationTest extends WebTestCase
{
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    public function testDomainReadQrIdentityReturnsSuccessPayload(): void
    {
        $client = static::createClient();

        $payloadValidator = $this->createMock(PayloadValidator::class);
        $payloadValidator
            ->expects(self::once())
            ->method('validatePayload')
            ->willReturn(['domain_read_qr_identity' => '{"domain":"example.test"}']);
        static::getContainer()->set(PayloadValidator::class, $payloadValidator);

        $requestMapper = $this->createMock(DomainReadQrIdentityRequestMapper::class);
        $requestMapper
            ->expects(self::once())
            ->method('map')
            ->willReturn(new ExtensionCredentialRequestDTO('example.test', 'public-1'));
        static::getContainer()->set(DomainReadQrIdentityRequestMapper::class, $requestMapper);

        $service = $this->createMock(DomainReadQrIdentityService::class);
        $service
            ->expects(self::once())
            ->method('handle')
            ->willReturn(['domainProcessId' => 'process-1']);
        static::getContainer()->set(DomainReadQrIdentityService::class, $service);

        $extensionListener = $this->createMock(HmacExtensionValidationListener::class);
        $extensionListener->expects(self::once())->method('onKernelController');
        static::getContainer()->set(HmacExtensionValidationListener::class, $extensionListener);

        $hmacValidator = $this->createMock(HmacValidator::class);
        $hmacValidator->expects(self::once())->method('validate')->willReturn(true);
        static::getContainer()->set(HmacValidator::class, $hmacValidator);

        $client->request(
            'POST',
            '/api/credential-hub/domain/read/qr-identity',
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
            'domainProcessId' => 'process-1',
        ], json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR));
    }
}
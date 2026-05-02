<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller\User;

use App\Controller\PayloadValidator\PayloadValidator;
use App\DTO\User\Qr\QrIdentityRequestDTO;
use App\DTO\User\Qr\QrIdentityResultDTO;
use App\Kernel;
use App\Service\Hmac\HmacValidator;
use App\Service\User\Qr\QrIdentityService;
use App\Service\User\Registration\RegistrationQrIdentityRequestMapper;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class RegistrationControllerIntegrationTest extends WebTestCase
{
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    public function testRegistrationQrIdentityReturnsGeneratedResponse(): void
    {
        $client = static::createClient();

        $payloadValidator = $this->createMock(PayloadValidator::class);
        $payloadValidator
            ->expects(self::once())
            ->method('getValidatedPayload')
            ->willReturn([
                'user_registration' => [
                    'corporatePublicId' => 'corp-1',
                ],
            ]);
        static::getContainer()->set(PayloadValidator::class, $payloadValidator);

        $requestMapper = $this->createMock(RegistrationQrIdentityRequestMapper::class);
        $requestMapper
            ->expects(self::once())
            ->method('map')
            ->willReturn(new QrIdentityRequestDTO([
                'corporatePublicId' => 'corp-1',
            ], 'registrationProcessId'));
        static::getContainer()->set(RegistrationQrIdentityRequestMapper::class, $requestMapper);

        $qrIdentityService = $this->createMock(QrIdentityService::class);
        $qrIdentityService
            ->expects(self::once())
            ->method('handle')
            ->willReturn(new QrIdentityResultDTO('encrypted-body', ['X-Test' => 'header']));
        static::getContainer()->set(QrIdentityService::class, $qrIdentityService);

        $hmacValidator = $this->createMock(HmacValidator::class);
        $hmacValidator
            ->expects(self::once())
            ->method('validate')
            ->willReturn(true);
        static::getContainer()->set(HmacValidator::class, $hmacValidator);

        $client->request(
            'POST',
            '/api/user/registration/qr-identity',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_AUTH' => 'HMAC client:signature:123',
            ],
            content: json_encode([
                'iv' => 'iv-value',
                'zeroIntrusionProyApi' => 'encrypted-payload',
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseIsSuccessful();
        self::assertSame('encrypted-body', (string) $client->getResponse()->getContent());
        self::assertSame('header', $client->getResponse()->headers->get('X-Test'));
    }

    public function testRegistrationQrIdentityReturnsStandardErrorPayloadOnValidationFailure(): void
    {
        $client = static::createClient();

        $payloadValidator = $this->createMock(PayloadValidator::class);
        $payloadValidator
            ->expects(self::once())
            ->method('getValidatedPayload')
            ->willThrowException(new \InvalidArgumentException('Invalid user registration payload.'));
        static::getContainer()->set(PayloadValidator::class, $payloadValidator);

        $hmacValidator = $this->createMock(HmacValidator::class);
        $hmacValidator
            ->expects(self::once())
            ->method('validate')
            ->willReturn(true);
        static::getContainer()->set(HmacValidator::class, $hmacValidator);

        $client->request(
            'POST',
            '/api/user/registration/qr-identity',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_AUTH' => 'HMAC client:signature:123',
            ],
            content: json_encode([
                'iv' => 'iv-value',
                'zeroIntrusionProyApi' => 'encrypted-payload',
            ], JSON_THROW_ON_ERROR),
        );

        self::assertResponseStatusCodeSame(400);
        self::assertSame([
            'success' => false,
            'error' => 'Invalid payload or missing required data.',
        ], json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR));
    }
}
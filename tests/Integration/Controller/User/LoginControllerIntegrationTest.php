<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller\User;

use App\Controller\PayloadValidator\PayloadValidator;
use App\DTO\User\Login\LoginQrIdentityRequestDTO;
use App\DTO\User\Login\LoginQrIdentityResultDTO;
use App\Kernel;
use App\Service\Hmac\HmacValidator;
use App\Service\User\Login\LoginQrIdentityRequestMapper;
use App\Service\User\Login\LoginQrIdentityService;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class LoginControllerIntegrationTest extends WebTestCase
{
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    public function testLoginQrIdentityReturnsGeneratedResponse(): void
    {
        $client = static::createClient();

        $payloadValidator = $this->createMock(PayloadValidator::class);
        $payloadValidator
            ->expects(self::once())
            ->method('getValidatedPayload')
            ->willReturn([
                'user_login' => [
                    'corporatePublicId' => 'corp-1',
                    'corporateAuthentication' => 'signature',
                    'domain' => 'https://example.test',
                    'userPublicId' => 'user-1',
                ],
            ]);
        static::getContainer()->set(PayloadValidator::class, $payloadValidator);

        $requestMapper = $this->createMock(LoginQrIdentityRequestMapper::class);
        $requestMapper
            ->expects(self::once())
            ->method('map')
            ->willReturn(LoginQrIdentityRequestDTO::fromArray([
                'corporatePublicId' => 'corp-1',
                'corporateAuthentication' => 'signature',
                'domain' => 'https://example.test',
                'userPublicId' => 'user-1',
            ]));
        static::getContainer()->set(LoginQrIdentityRequestMapper::class, $requestMapper);

        $loginQrIdentityService = $this->createMock(LoginQrIdentityService::class);
        $loginQrIdentityService
            ->expects(self::once())
            ->method('handle')
            ->willReturn(new LoginQrIdentityResultDTO(
                'encrypted-body',
                ['X-Test' => 'header'],
                (object) ['domainProcessId' => 'process-123'],
            ));
        static::getContainer()->set(LoginQrIdentityService::class, $loginQrIdentityService);

        $hmacValidator = $this->createMock(HmacValidator::class);
        $hmacValidator
            ->expects(self::once())
            ->method('validate')
            ->willReturn(true);
        static::getContainer()->set(HmacValidator::class, $hmacValidator);

        $client->request(
            'POST',
            '/api/user/login/qr-identity',
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

    public function testLoginQrIdentityReturnsStandardErrorPayloadOnValidationFailure(): void
    {
        $client = static::createClient();

        $payloadValidator = $this->createMock(PayloadValidator::class);
        $payloadValidator
            ->expects(self::once())
            ->method('getValidatedPayload')
            ->willThrowException(new \InvalidArgumentException('Invalid user login payload.'));
        static::getContainer()->set(PayloadValidator::class, $payloadValidator);

        $hmacValidator = $this->createMock(HmacValidator::class);
        $hmacValidator
            ->expects(self::once())
            ->method('validate')
            ->willReturn(true);
        static::getContainer()->set(HmacValidator::class, $hmacValidator);

        $client->request(
            'POST',
            '/api/user/login/qr-identity',
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
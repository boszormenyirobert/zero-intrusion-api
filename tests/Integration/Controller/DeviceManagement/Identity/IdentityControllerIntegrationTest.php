<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller\DeviceManagement\Identity;

use App\Controller\PayloadValidator\PayloadValidator;
use App\DTO\Device\Identity\RecoverySettingsRequestDTO;
use App\Kernel;
use App\Service\Device\Identity\FirstSecretService;
use App\Service\Device\Identity\RecoverySettingsRequestMapper;
use App\Service\Device\Identity\RecoverySettingsService;
use App\Service\Hmac\HmacValidator;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class IdentityControllerIntegrationTest extends WebTestCase
{
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    public function testCreateSecretReturnsGeneratedIdentityPayload(): void
    {
        $client = static::createClient();

        $payloadValidator = $this->createMock(PayloadValidator::class);
        $payloadValidator
            ->expects(self::once())
            ->method('validatePayload')
            ->willReturn(['firstSecret' => 'no-data']);
        static::getContainer()->set(PayloadValidator::class, $payloadValidator);

        $firstSecretService = $this->createMock(FirstSecretService::class);
        $firstSecretService
            ->expects(self::once())
            ->method('handle')
            ->willReturn(['privateSecret' => ['publicId' => 'public-1']]);
        static::getContainer()->set(FirstSecretService::class, $firstSecretService);

        $hmacValidator = $this->createMock(HmacValidator::class);
        $hmacValidator
            ->expects(self::once())
            ->method('validate')
            ->willReturn(true);
        static::getContainer()->set(HmacValidator::class, $hmacValidator);

        $client->request(
            'POST',
            '/api/secret/new',
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
        self::assertSame([
            'privateSecret' => ['publicId' => 'public-1'],
        ], json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR));
    }

    public function testCreateSecretReturnsStandardErrorPayloadWhenValidationFails(): void
    {
        $client = static::createClient();

        $payloadValidator = $this->createMock(PayloadValidator::class);
        $payloadValidator
            ->expects(self::once())
            ->method('validatePayload')
            ->willThrowException(new \InvalidArgumentException('Invalid first secret payload.'));
        static::getContainer()->set(PayloadValidator::class, $payloadValidator);

        $hmacValidator = $this->createMock(HmacValidator::class);
        $hmacValidator
            ->expects(self::once())
            ->method('validate')
            ->willReturn(true);
        static::getContainer()->set(HmacValidator::class, $hmacValidator);

        $client->request(
            'POST',
            '/api/secret/new',
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

    public function testSetRecoveryDataReturnsStandardErrorPayloadWhenValidationFails(): void
    {
        $client = static::createClient();

        $payloadValidator = $this->createMock(PayloadValidator::class);
        $payloadValidator
            ->expects(self::once())
            ->method('validatePayload')
            ->willThrowException(new \InvalidArgumentException('Invalid recovery settings payload.'));
        static::getContainer()->set(PayloadValidator::class, $payloadValidator);

        $requestMapper = $this->createMock(RecoverySettingsRequestMapper::class);
        $requestMapper->expects(self::never())->method('map');
        static::getContainer()->set(RecoverySettingsRequestMapper::class, $requestMapper);

        $recoverySettingsService = $this->createMock(RecoverySettingsService::class);
        $recoverySettingsService->expects(self::never())->method('handle');
        static::getContainer()->set(RecoverySettingsService::class, $recoverySettingsService);

        $hmacValidator = $this->createMock(HmacValidator::class);
        $hmacValidator
            ->expects(self::once())
            ->method('validate')
            ->willReturn(true);
        static::getContainer()->set(HmacValidator::class, $hmacValidator);

        $client->request(
            'POST',
            '/api/secret/recovery-settings',
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
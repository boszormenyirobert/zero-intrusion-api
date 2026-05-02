<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller\DeviceManagement\Restore;

use App\Controller\PayloadValidator\PayloadValidator;
use App\Kernel;
use App\Service\Device\Restore\ReplaceDevicePinRequestMapper;
use App\Service\Device\Restore\ReplaceDevicePinService;
use App\Service\Device\Restore\ReplaceDeviceRequestMapper;
use App\Service\Device\Restore\ReplaceDeviceService;
use App\Service\Hmac\HmacValidator;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class RestoreControllerIntegrationTest extends WebTestCase
{
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    public function testReplaceDeviceReturnsStandardErrorPayloadWhenValidationFails(): void
    {
        $client = static::createClient();

        $payloadValidator = $this->createMock(PayloadValidator::class);
        $payloadValidator
            ->expects(self::once())
            ->method('validatePayload')
            ->willThrowException(new \InvalidArgumentException('Invalid replace device payload.'));
        static::getContainer()->set(PayloadValidator::class, $payloadValidator);

        $replaceMapper = $this->createMock(ReplaceDeviceRequestMapper::class);
        $replaceMapper->expects(self::never())->method('map');
        static::getContainer()->set(ReplaceDeviceRequestMapper::class, $replaceMapper);

        $replaceService = $this->createMock(ReplaceDeviceService::class);
        $replaceService->expects(self::never())->method('handle');
        static::getContainer()->set(ReplaceDeviceService::class, $replaceService);

        $hmacValidator = $this->createMock(HmacValidator::class);
        $hmacValidator
            ->expects(self::once())
            ->method('validate')
            ->willReturn(true);
        static::getContainer()->set(HmacValidator::class, $hmacValidator);

        $client->request(
            'POST',
            '/api/device/replace',
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

    public function testReplaceDevicePinReturnsStandardErrorPayloadWhenValidationFails(): void
    {
        $client = static::createClient();

        $payloadValidator = $this->createMock(PayloadValidator::class);
        $payloadValidator
            ->expects(self::once())
            ->method('validatePayload')
            ->willThrowException(new \InvalidArgumentException('Invalid restore pin payload.'));
        static::getContainer()->set(PayloadValidator::class, $payloadValidator);

        $pinMapper = $this->createMock(ReplaceDevicePinRequestMapper::class);
        $pinMapper->expects(self::never())->method('map');
        static::getContainer()->set(ReplaceDevicePinRequestMapper::class, $pinMapper);

        $pinService = $this->createMock(ReplaceDevicePinService::class);
        $pinService->expects(self::never())->method('handle');
        static::getContainer()->set(ReplaceDevicePinService::class, $pinService);

        $hmacValidator = $this->createMock(HmacValidator::class);
        $hmacValidator
            ->expects(self::once())
            ->method('validate')
            ->willReturn(true);
        static::getContainer()->set(HmacValidator::class, $hmacValidator);

        $client->request(
            'POST',
            '/api/device/replace/pin',
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
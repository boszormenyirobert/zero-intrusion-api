<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller\DeviceManagement\Nfc;

use App\EventListener\HmacDesktopValidationListener;
use App\DTO\Device\Nfc\NfcDecryptRequestDTO;
use App\Kernel;
use App\Service\Device\Nfc\NfcDecryptRequestMapper;
use App\Service\Device\Nfc\NfcDecryptService;
use App\Service\Device\Nfc\NfcRequestResolver;
use App\Service\Device\Nfc\NfcUsersService;
use App\Service\Hmac\HmacValidator;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class NfcControllerIntegrationTest extends WebTestCase
{
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    public function testGetNfcUsersRouteReturnsUsersPayload(): void
    {
        $client = static::createClient();

        $usersService = $this->createMock(NfcUsersService::class);
        $usersService
            ->expects(self::once())
            ->method('handle')
            ->willReturn(['users' => [['puID' => 'public-1']]]);
        static::getContainer()->set(NfcUsersService::class, $usersService);

        $desktopListener = $this->createMock(HmacDesktopValidationListener::class);
        $desktopListener->expects(self::once())->method('onKernelController');
        static::getContainer()->set(HmacDesktopValidationListener::class, $desktopListener);

        $hmacValidator = $this->createMock(HmacValidator::class);
        $hmacValidator
            ->expects(self::once())
            ->method('validate')
            ->willReturn(true);
        static::getContainer()->set(HmacValidator::class, $hmacValidator);

        $client->request(
            'POST',
            '/api/nfc/users',
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
            'users' => [['puID' => 'public-1']],
        ], json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR));
    }

    public function testDecryptRouteReturnsStandardErrorPayloadWhenResolverFails(): void
    {
        $client = static::createClient();

        $requestResolver = $this->createMock(NfcRequestResolver::class);
        $requestResolver
            ->expects(self::once())
            ->method('resolve')
            ->willThrowException(new \InvalidArgumentException('Invalid NFC payload.'));
        static::getContainer()->set(NfcRequestResolver::class, $requestResolver);

        $decryptMapper = $this->createMock(NfcDecryptRequestMapper::class);
        $decryptMapper->expects(self::never())->method('map');
        static::getContainer()->set(NfcDecryptRequestMapper::class, $decryptMapper);

        $decryptService = $this->createMock(NfcDecryptService::class);
        $decryptService->expects(self::never())->method('handle');
        static::getContainer()->set(NfcDecryptService::class, $decryptService);

        $desktopListener = $this->createMock(HmacDesktopValidationListener::class);
        $desktopListener->expects(self::once())->method('onKernelController');
        static::getContainer()->set(HmacDesktopValidationListener::class, $desktopListener);

        $hmacValidator = $this->createMock(HmacValidator::class);
        $hmacValidator
            ->expects(self::once())
            ->method('validate')
            ->willReturn(true);
        static::getContainer()->set(HmacValidator::class, $hmacValidator);

        $client->request(
            'POST',
            '/api/nfc/decrypt',
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

    public function testDecryptRouteReturnsResolvedPayload(): void
    {
        $client = static::createClient();

        $requestResolver = $this->createMock(NfcRequestResolver::class);
        $requestResolver
            ->expects(self::once())
            ->method('resolve')
            ->willReturn(['api_nfc_decrypt' => ['userPublicId' => 'public-1', 'nfcData' => 'encrypted-payload']]);
        static::getContainer()->set(NfcRequestResolver::class, $requestResolver);

        $decryptMapper = $this->createMock(NfcDecryptRequestMapper::class);
        $decryptMapper
            ->expects(self::once())
            ->method('map')
            ->willReturn(new NfcDecryptRequestDTO('public-1', 'corp-1', 'encrypted-payload'));
        static::getContainer()->set(NfcDecryptRequestMapper::class, $decryptMapper);

        $decryptService = $this->createMock(NfcDecryptService::class);
        $decryptService
            ->expects(self::once())
            ->method('handle')
            ->willReturn(['puID' => 'public-1']);
        static::getContainer()->set(NfcDecryptService::class, $decryptService);

        $desktopListener = $this->createMock(HmacDesktopValidationListener::class);
        $desktopListener->expects(self::once())->method('onKernelController');
        static::getContainer()->set(HmacDesktopValidationListener::class, $desktopListener);

        $hmacValidator = $this->createMock(HmacValidator::class);
        $hmacValidator
            ->expects(self::once())
            ->method('validate')
            ->willReturn(true);
        static::getContainer()->set(HmacValidator::class, $hmacValidator);

        $client->request(
            'POST',
            '/api/nfc/decrypt',
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
            'puID' => 'public-1',
        ], json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR));
    }
}
<?php

declare(strict_types=1);

namespace App\Tests\Service\Firebase;

use App\Entity\Identity;
use App\Repository\IdentityRepository;
use App\Service\Firebase\FirebaseService;
use App\Service\Identity\Database\CrypterDatabaseIdentityService;
use App\Service\Payload\JsonPayloadDecoder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;

final class FirebaseServiceTest extends TestCase
{
    public function testManageFcmDoesNothingWhenIdentityIsMissing(): void
    {
        $repository = $this->createMock(IdentityRepository::class);
        $repository->expects(self::once())->method('findOneBy')->with(['publicId' => 'public-1'])->willReturn(null);

        $service = new FirebaseService(
            $this->createMock(ContainerBagInterface::class),
            $repository,
            $this->createMock(CrypterDatabaseIdentityService::class),
            $this->createMock(LoggerInterface::class),
            new JsonPayloadDecoder(),
        );

        $service->manageFcm('public-1', 'Title', 'Body', ['qr' => 'payload']);
        self::assertTrue(true);
    }

    public function testManageFcmDecryptsEachTokenAndDelegatesToSendFcmMessage(): void
    {
        $identity = (new Identity())
            ->setIv(base64_encode(random_bytes(16)))
            ->setFcmToken(['token-a', 'token-b']);

        $repository = $this->createMock(IdentityRepository::class);
        $repository->method('findOneBy')->willReturn($identity);

        $crypter = $this->createMock(CrypterDatabaseIdentityService::class);
        $crypter
            ->expects(self::exactly(2))
            ->method('decryptData')
            ->willReturnOnConsecutiveCalls('decrypted-token-a', 'decrypted-token-b');

        $service = $this->getMockBuilder(FirebaseService::class)
            ->setConstructorArgs([
                $this->createMock(ContainerBagInterface::class),
                $repository,
                $crypter,
                $this->createMock(LoggerInterface::class),
                new JsonPayloadDecoder(),
            ])
            ->onlyMethods(['sendFcmMessage'])
            ->getMock();

        $service
            ->expects(self::exactly(2))
            ->method('sendFcmMessage')
            ->withConsecutive(
                ['decrypted-token-a', 'Title', 'Body', ['qr' => 'payload']],
                ['decrypted-token-b', 'Title', 'Body', ['qr' => 'payload']],
            );

        $service->manageFcm('public-1', 'Title', 'Body', ['qr' => 'payload']);
    }

    public function testSendFcmMessageReturnsEarlyWhenJwtGenerationFails(): void
    {
        $params = $this->createMock(ContainerBagInterface::class);
        $params
            ->method('get')
            ->willReturnMap([
                ['FIREBASE_CLIENT_EMAIL', 'firebase@example.test'],
                ['FIREBASE_PRIVATE_KEY', 'invalid-private-key'],
                ['FIREBASE_TOKEN_URI', 'https://oauth.example.test/token'],
            ]);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::atLeastOnce())->method('critical');

        $service = new FirebaseService(
            $params,
            $this->createMock(IdentityRepository::class),
            $this->createMock(CrypterDatabaseIdentityService::class),
            $logger,
            new JsonPayloadDecoder(),
        );

        set_error_handler(static function (int $severity, string $message): bool {
            return $severity === E_WARNING && str_contains($message, 'openssl_sign():');
        });

        try {
            $service->sendFcmMessage('device-token-123456', 'Title', 'Body', ['qr' => 'payload']);
        } finally {
            restore_error_handler();
        }

        self::assertTrue(true);
    }

    public function testPrivateHelpersHandleMaskingAndNullJwt(): void
    {
        $service = new FirebaseService(
            $this->createMock(ContainerBagInterface::class),
            $this->createMock(IdentityRepository::class),
            $this->createMock(CrypterDatabaseIdentityService::class),
            $this->createMock(LoggerInterface::class),
            new JsonPayloadDecoder(),
        );

        $maskToken = new \ReflectionMethod($service, 'maskToken');
        $getAccessToken = new \ReflectionMethod($service, 'getAccessToken');

        self::assertSame('empty-token', $maskToken->invoke($service, null));
        self::assertSame('*****', $maskToken->invoke($service, 'short'));
        self::assertSame('123456...cdef', $maskToken->invoke($service, '1234567890abcdef'));
        self::assertNull($getAccessToken->invoke($service, null));
    }

    public function testCreateMessagePayloadAcceptsObjectQrData(): void
    {
        $service = new FirebaseService(
            $this->createMock(ContainerBagInterface::class),
            $this->createMock(IdentityRepository::class),
            $this->createMock(CrypterDatabaseIdentityService::class),
            $this->createMock(LoggerInterface::class),
            new JsonPayloadDecoder(),
        );

        $createMessagePayload = new \ReflectionMethod($service, 'createMessagePayload');
        $qrData = (object) ['domainProcessId' => 'process-1'];

        $payload = $createMessagePayload->invoke($service, 'device-token', 'Title', 'Body', $qrData);

        self::assertSame('{"domainProcessId":"process-1"}', $payload['message']['data']['qrData']);
    }
}
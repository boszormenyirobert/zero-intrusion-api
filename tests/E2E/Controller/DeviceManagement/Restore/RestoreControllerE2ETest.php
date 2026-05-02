<?php

declare(strict_types=1);

namespace App\Tests\E2E\Controller\DeviceManagement\Restore;

use App\Kernel;
use App\Service\Crypters\CrypterService;
use App\Service\Identity\IdentityService;
use App\Service\Restore\RestoreService;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

final class RestoreControllerE2ETest extends WebTestCase
{
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    public function testReplaceDeviceExecutesControllerPipelineToSuccessResponse(): void
    {
        $client = static::createClient();

        $recoveryData = new class() {
            public function getEmail(): string { return 'user@example.test'; }
            public function getPublicId(): string { return 'public-1'; }
            public function getPrivateId(): string { return 'private-1'; }
            public function getSecret(): string { return 'secret-1'; }
        };

        $identityService = $this->createMock(IdentityService::class);
        $identityService
            ->expects(self::once())
            ->method('getSecret')
            ->with([
                'email' => 'user@example.test',
                'phone' => '+36123456789',
            ])
            ->willReturn($recoveryData);
        static::getContainer()->set(IdentityService::class, $identityService);

        $restoreService = $this->createMock(RestoreService::class);
        $restoreService
            ->expects(self::once())
            ->method('recoveryNotification')
            ->with($recoveryData)
            ->willReturn([
                'success' => true,
                'deviceHash' => 'hash-1',
                'message' => 'ok',
            ]);
        static::getContainer()->set(RestoreService::class, $restoreService);

        $this->requestJsonSigned($client, 'POST', '/api/device/replace', [
            'replaceDevice' => [
                'email' => 'user@example.test',
                'phone' => '+36123456789',
            ],
        ]);

        self::assertResponseIsSuccessful();
        self::assertSame([
            'success' => true,
            'deviceHash' => 'hash-1',
            'message' => 'ok',
        ], json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR));
    }

    public function testReplaceDevicePinExecutesControllerPipelineToSuccessResponse(): void
    {
        $client = static::createClient();

        $restoreService = $this->createMock(RestoreService::class);
        $restoreService
            ->expects(self::once())
            ->method('replaceValidation')
            ->with([
                'restorePin' => [
                    'data' => ['pin' => '123456'],
                    'replaceHash' => 'hash-1',
                ],
            ])
            ->willReturn([
                'publicId' => 'public-1',
                'privateId' => 'private-1',
                'secret' => 'secret-1',
            ]);
        static::getContainer()->set(RestoreService::class, $restoreService);

        $this->requestJsonSigned($client, 'POST', '/api/device/replace/pin', [
            'restorePin' => [
                'data' => ['pin' => '123456'],
                'replaceHash' => 'hash-1',
            ],
        ]);

        self::assertResponseIsSuccessful();
        self::assertSame([
            'publicId' => 'public-1',
            'privateId' => 'private-1',
            'secret' => 'secret-1',
        ], json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR));
    }

    private function requestJsonSigned(KernelBrowser $client, string $method, string $uri, array $payload): void
    {
        $outerIv = 'outer-iv';
        /** @var CrypterService $crypterService */
        $crypterService = static::getContainer()->get(CrypterService::class);
        /** @var ParameterBagInterface $params */
        $params = static::getContainer()->get(ParameterBagInterface::class);

        $crypterService->setData($payload);
        $encryptedPayload = $crypterService->encryptData();
        $timestamp = time();
        $apiKey = (string) $params->get('SERVICE_API_KEY');
        $secret = (string) $params->get('SERVICE_API_SECRET');
        $signature = hash_hmac('sha256', $encryptedPayload . '|' . $outerIv, $secret);

        $client->request(
            $method,
            $uri,
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_AUTH' => sprintf('HMAC %s:%s:%d', $apiKey, $signature, $timestamp),
            ],
            content: json_encode([
                'iv' => $outerIv,
                'zeroIntrusionProyApi' => $encryptedPayload,
            ], JSON_THROW_ON_ERROR),
        );
    }
}
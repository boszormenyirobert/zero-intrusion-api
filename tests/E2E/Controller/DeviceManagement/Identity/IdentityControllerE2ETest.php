<?php

declare(strict_types=1);

namespace App\Tests\E2E\Controller\DeviceManagement\Identity;

use App\Kernel;
use App\Service\Crypters\CrypterService;
use App\Service\Identity\DTO\IdentityKeyDTO;
use App\Service\Identity\IdentityService;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

final class IdentityControllerE2ETest extends WebTestCase
{
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    public function testCreateSecretExecutesControllerPipelineToSuccessResponse(): void
    {
        $client = static::createClient();

        $key = new IdentityKeyDTO('public-1', 'private-1', 'secret-1', 'credential-secret-1', 'nfc-key-1');

        $identityService = $this->createMock(IdentityService::class);
        $identityService
            ->expects(self::once())
            ->method('getKey')
            ->willReturn($key);
        static::getContainer()->set(IdentityService::class, $identityService);

        $this->requestJsonSigned($client, 'POST', '/api/secret/new', [
            'firstSecret' => [
                'init' => true,
            ],
        ]);

        self::assertResponseIsSuccessful();
        self::assertSame([
            'privateSecret' => [
                'publicId' => 'public-1',
                'privateId' => 'private-1',
                'secret' => 'secret-1',
                'credentialSecret' => 'credential-secret-1',
                'email' => '--not-define-registration-process-one',
                'phone' => '--not-define-registration-process-one',
            ],
        ], json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR));
    }

    public function testSetRecoveryDataExecutesControllerPipelineToSuccessResponse(): void
    {
        $client = static::createClient();

        $identityService = $this->createMock(IdentityService::class);
        $identityService
            ->expects(self::once())
            ->method('updateIdentityRecoverySettings')
            ->with([
                'publicId' => 'public-1',
                'privateId' => 'private-enc',
                'email' => 'user@example.test',
                'phone' => '+36123456789',
                'privacyPolicy' => true,
                'fcmToken' => 'fcm-token',
            ]);
        static::getContainer()->set(IdentityService::class, $identityService);

        $this->requestJsonSigned($client, 'POST', '/api/secret/recovery-settings', [
            'recoverySettings' => [
                'publicId' => 'public-1',
                'privateId' => 'private-enc',
                'email' => 'user@example.test',
                'phone' => '+36123456789',
                'privacyPolicy' => true,
                'fcmToken' => 'fcm-token',
            ],
        ]);

        self::assertResponseIsSuccessful();
        self::assertSame([
            'success' => true,
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
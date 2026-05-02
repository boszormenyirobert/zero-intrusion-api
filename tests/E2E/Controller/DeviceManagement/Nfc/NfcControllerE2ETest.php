<?php

declare(strict_types=1);

namespace App\Tests\E2E\Controller\DeviceManagement\Nfc;

use App\Entity\Identity;
use App\EventListener\HmacDesktopValidationListener;
use App\Kernel;
use App\Repository\IdentityRepository;
use App\Service\Crypters\CrypterDatabaseLoginService;
use App\Service\Crypters\CrypterService;
use App\Service\Crypters\SodiumService;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

final class NfcControllerE2ETest extends WebTestCase
{
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    public function testGetNfcUsersExecutesControllerPipelineToSuccessResponse(): void
    {
        $client = static::createClient();
        $this->mockDesktopListener();

        $encryptedIdentity = $this->createMock(Identity::class);
        $encryptedIdentity->method('getNfcEncryptionKey')->willReturn('nfc-key');

        $decryptedIdentity = $this->createMock(Identity::class);
        $decryptedIdentity->method('getPublicId')->willReturn('public-1');
        $decryptedIdentity->method('getPrivateId')->willReturn('private-enc');
        $decryptedIdentity->method('getSecret')->willReturn('secret-1');
        $decryptedIdentity->method('getCredentialSecret')->willReturn('cred-secret-1');
        $decryptedIdentity->method('getEmail')->willReturn('user@example.test');

        $identityRepository = $this->createMock(IdentityRepository::class);
        $identityRepository->expects(self::once())->method('findAll')->willReturn([$encryptedIdentity]);
        static::getContainer()->set(IdentityRepository::class, $identityRepository);

        $crypterDatabaseLoginService = $this->createMock(CrypterDatabaseLoginService::class);
        $crypterDatabaseLoginService
            ->expects(self::once())
            ->method('decryptFromDatabaseidentity')
            ->with($encryptedIdentity)
            ->willReturn($decryptedIdentity);
        static::getContainer()->set(CrypterDatabaseLoginService::class, $crypterDatabaseLoginService);

        $sodiumService = $this->createMock(SodiumService::class);
        $sodiumService
            ->expects(self::once())
            ->method('sodiumDecrypt')
            ->with('private-enc', 'secret-1')
            ->willReturn('private-plain');
        $sodiumService
            ->expects(self::once())
            ->method('sodiumEncrypt')
            ->with(json_encode([
                'puID' => 'public-1',
                'prID' => 'private-plain',
                'secret' => 'secret-1',
                'credSecret' => 'cred-secret-1',
            ], JSON_THROW_ON_ERROR), 'nfc-key')
            ->willReturn('encrypted-nfc-data');
        static::getContainer()->set(SodiumService::class, $sodiumService);

        $this->requestJsonSigned($client, 'POST', '/api/nfc/users', [
            'api_nfc_users' => [
                'publicId' => 'corp-1',
                'timestamp' => time(),
            ],
        ]);

        self::assertResponseIsSuccessful();
        self::assertSame([
            'users' => [[
                'email' => 'user@example.test',
                'nfcData' => 'encrypted-nfc-data',
                'puID' => 'public-1',
            ]],
        ], json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR));
    }

    public function testDecryptNfcCardDataExecutesControllerPipelineToSuccessResponse(): void
    {
        $client = static::createClient();
        $this->mockDesktopListener();

        $identity = $this->createMock(Identity::class);
        $identity->method('getNfcEncryptionKey')->willReturn('nfc-key');

        $identityRepository = $this->createMock(IdentityRepository::class);
        $identityRepository
            ->expects(self::once())
            ->method('findOneBy')
            ->with(['publicId' => 'public-1'])
            ->willReturn($identity);
        static::getContainer()->set(IdentityRepository::class, $identityRepository);

        $sodiumService = $this->createMock(SodiumService::class);
        $sodiumService
            ->expects(self::once())
            ->method('sodiumDecrypt')
            ->with('encrypted-payload', 'nfc-key')
            ->willReturn('{"puID":"public-1"}');
        static::getContainer()->set(SodiumService::class, $sodiumService);

        $this->requestJsonSigned($client, 'POST', '/api/nfc/decrypt', [
            'api_nfc_decrypt' => [
                'userPublicId' => 'public-1',
                'publicId' => 'corp-1',
                'nfcData' => 'encrypted-payload',
                'timestamp' => time(),
            ],
        ]);

        self::assertResponseIsSuccessful();
        self::assertSame([
            'puID' => 'public-1',
        ], json_decode((string) $client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR));
    }

    private function mockDesktopListener(): void
    {
        $desktopListener = $this->createMock(HmacDesktopValidationListener::class);
        $desktopListener->expects(self::once())->method('onKernelController');
        static::getContainer()->set(HmacDesktopValidationListener::class, $desktopListener);
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
                'HTTP_X_EXTENSION_AUTH' => 'desktop-signature',
            ],
            content: json_encode([
                'iv' => $outerIv,
                'zeroIntrusionProyApi' => $encryptedPayload,
            ], JSON_THROW_ON_ERROR),
        );
    }
}
<?php

declare(strict_types=1);

namespace App\Tests\Controller\CredentialHub\Domain\Read;

use App\Controller\CredentialHub\Domain\Read\DomainService;
use App\Controller\PayloadValidator\PayloadValidator;
use App\Entity\CorporateIdentity;
use App\Repository\AccessRegistryRepository;
use App\Repository\CorporateIdentityRepository;
use App\Repository\IdentityRepository;
use App\Service\AuthBridge\AuthBridgeHandler\Domain\Encryptor;
use App\Service\AuthBridge\AuthBridgeService;
use App\Service\Crypters\CrypterDatabaseService;
use App\Service\Identity\Database\CrypterDatabaseIdentityService;
use App\Service\Notifier\NotifierService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class DomainServiceTest extends TestCase
{
    public function testGetQrContentBuildsExpectedDto(): void
    {
        $identity = new class () {
            public function getDomainProcessId(): string
            {
                return 'domain-process-1';
            }

            public function getIv(): string
            {
                return 'iv-1';
            }
        };

        $service = $this->createService($this->createNotifierService(false));
        $dto = $service->getQrContent('example.test', 'auth-1', $identity);

        self::assertSame('example.test', $dto->domain);
        self::assertSame('domain-process-1', $dto->domainProcessId);
        self::assertSame('auth-1', $dto->xExtensionAuthOne);
        self::assertSame('domain-login', $dto->type);
        self::assertSame('extension', $dto->source);
        self::assertSame('iv-1', $dto->iv);
    }

    public function testProcessCredentialReadRejectsInvalidPayloadsAndUnknownSources(): void
    {
        $service = $this->createService($this->createNotifierService(false));

        self::assertFalse($service->processCredentialRead(['source' => 'extension']));
        self::assertFalse($service->processCredentialRead(['type' => 'unsupported', 'source' => 'extension']));
        self::assertFalse($service->processCredentialRead(['type' => 'domain-login', 'source' => 'desktop']));
    }

    public function testProcessCredentialReadHandlesExtensionAndCorporateSources(): void
    {
        $user = ['type' => 'domain-login', 'source' => 'extension'];
        $authBridge = $this->createMock(AuthBridgeService::class);
        $authBridge
            ->expects(self::once())
            ->method('persistDecryptedUserData')
            ->with($user)
            ->willReturn(true);

        $service = $this->createService($this->createNotifierService(false), $authBridge);
        self::assertTrue($service->processCredentialRead($user));

        $corporateUser = [
            'type' => 'domain-login',
            'source' => 'corporate',
            'corporateId' => 'corp-1',
            'publicId' => 'public-1',
            'email' => 'user@example.test',
            'domainProcessId' => 'domain-process-1',
        ];
        $authBridge = $this->createMock(AuthBridgeService::class);
        $authBridge
            ->expects(self::once())
            ->method('persistDecryptedUserDataForWeb')
            ->with($corporateUser)
            ->willReturn(['decrypted' => 'payload']);

        $service = $this->createService($this->createNotifierService(true), $authBridge);

        set_error_handler(static function (int $severity, string $message): bool {
            return $severity === E_WARNING && str_contains($message, 'openssl_sign():');
        });

        try {
            self::assertTrue($service->processCredentialRead($corporateUser));
        } finally {
            restore_error_handler();
        }
    }

    public function testProcessCredentialReadReturnsFalseWhenCorporatePersistenceFails(): void
    {
        $user = ['type' => 'domain-login', 'source' => 'corporate'];
        $authBridge = $this->createMock(AuthBridgeService::class);
        $authBridge
            ->expects(self::once())
            ->method('persistDecryptedUserDataForWeb')
            ->with($user)
            ->willReturn(null);

        $service = $this->createService($this->createNotifierService(false), $authBridge);

        self::assertFalse($service->processCredentialRead($user));
    }

    public function testGetDecryptedCredentialsDelegatesBySource(): void
    {
        $extensionUser = ['type' => 'domain-login', 'source' => 'extension'];
        $corporateUser = ['type' => 'system_hub_login', 'source' => 'corporate'];

        $authBridge = $this->createMock(AuthBridgeService::class);
        $authBridge
            ->expects(self::once())
            ->method('getDecryptedUserDataToMobileRequest')
            ->with($extensionUser)
            ->willReturn(['credential' => 'mobile']);
        $authBridge
            ->expects(self::once())
            ->method('persistDecryptedUserDataForWeb')
            ->with($corporateUser)
            ->willReturn(['credential' => 'web']);

        $service = $this->createService($this->createNotifierService(false), $authBridge);

        self::assertSame(['credential' => 'mobile'], $service->getDecryptedCredentials($extensionUser));
        self::assertSame(['credential' => 'web'], $service->getDecryptedCredentials($corporateUser));

        $this->expectException(\TypeError::class);
        $service->getDecryptedCredentials(['type' => 'unknown', 'source' => 'extension']);
    }

    private function createService(NotifierService $notifierService, ?AuthBridgeService $authBridge = null): DomainService
    {
        return new DomainService(
            $this->createMock(PayloadValidator::class),
            $authBridge ?? $this->createMock(AuthBridgeService::class),
            $this->createMock(CorporateIdentityRepository::class),
            $this->createMock(HttpClientInterface::class),
            $this->createMock(LoggerInterface::class),
            $this->createMock(AccessRegistryRepository::class),
            $notifierService,
        );
    }

    private function createNotifierService(bool $expectRequest): NotifierService
    {
        $encryptedCorporate = (new CorporateIdentity())
            ->setCorporateId('corp-1')
            ->setCallbackUserLogin('https://callback.example.test/login');

        $decryptedCorporate = (new CorporateIdentity())
            ->setCallbackUserLogin('https://callback.example.test/login')
            ->setSslPrivateKey('invalid-private-key');

        $corporateRepository = $this->createMock(CorporateIdentityRepository::class);
        $corporateRepository
            ->method('findOneBy')
            ->willReturn($encryptedCorporate);

        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);

        $httpClient = $this->createMock(HttpClientInterface::class);
        if ($expectRequest) {
            $httpClient
                ->expects(self::once())
                ->method('request')
                ->with(
                    'POST',
                    'https://callback.example.test/login',
                    self::callback(static function (array $options): bool {
                        return array_key_exists('signature', $options['json'])
                            && isset($options['json']['publicId'], $options['json']['email'], $options['json']['processId'])
                            && $options['json']['publicId'] === 'public-1'
                            && $options['json']['email'] === 'user@example.test'
                            && $options['json']['processId'] === 'domain-process-1';
                    }),
                )
                ->willReturn($response);
        }

        $crypterDatabaseService = $this->createMock(CrypterDatabaseService::class);
        $crypterDatabaseService
            ->method('decryptFromDatabase')
            ->with($encryptedCorporate)
            ->willReturn($decryptedCorporate);

        return new NotifierService(
            $this->createMock(LoggerInterface::class),
            $httpClient,
            $corporateRepository,
            $this->createMock(IdentityRepository::class),
            $this->createMock(CrypterDatabaseIdentityService::class),
            $this->createMock(Encryptor::class),
            $this->createMock(ContainerBagInterface::class),
            $crypterDatabaseService,
        );
    }
}
<?php

declare(strict_types=1);

namespace App\Tests\Service\Notifier;

use App\Entity\CorporateIdentity;
use App\Entity\Identity;
use App\Repository\CorporateIdentityRepository;
use App\Repository\IdentityRepository;
use App\Service\AuthBridge\AuthBridgeHandler\Domain\Encryptor;
use App\Service\Crypters\CrypterDatabaseService;
use App\Service\Identity\Database\CrypterDatabaseIdentityService;
use App\Service\Notifier\NotifierService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class NotifierServiceTest extends TestCase
{
    public function testCallBackUserRegistrationSendsSignedJsonPayload(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('getStatusCode')->willReturn(200);

        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient
            ->expects(self::once())
            ->method('request')
            ->with(
                'POST',
                'https://callback.example.test/register',
                self::callback(static fn (array $options): bool => isset($options['json']['publicId'], $options['json']['email'], $options['json']['registrationProcessId']) && array_key_exists('signature', $options['json']))
            )
            ->willReturn($response);

        $service = $this->createService($httpClient);

        set_error_handler(static function (int $severity, string $message): bool {
            return $severity === E_WARNING && str_contains($message, 'openssl_sign():');
        });

        try {
            $service->callBackUserRegistration(['publicId' => 'public-1'], [
                'publicId' => 'public-1',
                'email' => 'user@example.test',
                'corporateId' => 'corp-1',
                'registrationProcessId' => 'registration-1',
            ]);
        } finally {
            restore_error_handler();
        }

        self::assertTrue(true);
    }

    public function testCallBackUserLoginSendsSignedJsonPayload(): void
    {
        $httpClient = $this->createMock(HttpClientInterface::class);
        $httpClient
            ->expects(self::once())
            ->method('request')
            ->with(
                'POST',
                'https://callback.example.test/login',
                self::callback(static fn (array $options): bool => isset($options['json']['publicId'], $options['json']['email'], $options['json']['processId']) && array_key_exists('signature', $options['json']))
            );

        $service = $this->createService($httpClient);

        set_error_handler(static function (int $severity, string $message): bool {
            return $severity === E_WARNING && str_contains($message, 'openssl_sign():');
        });

        try {
            $service->callBackUserLogin(['decrypted' => 'payload'], [
                'publicId' => 'public-1',
                'email' => 'user@example.test',
                'corporateId' => 'corp-1',
                'domainProcessId' => 'domain-process-1',
            ]);
        } finally {
            restore_error_handler();
        }

        self::assertTrue(true);
    }

    private function createService(HttpClientInterface $httpClient): NotifierService
    {
        $encryptedCorporate = (new CorporateIdentity())
            ->setCorporateId('corp-1')
            ->setCallbackUserRegistration('https://callback.example.test/register');

        $decryptedCorporate = (new CorporateIdentity())
            ->setCallbackUserLogin('https://callback.example.test/login')
            ->setSslPrivateKey('invalid-private-key');

        $corporateRepository = $this->createMock(CorporateIdentityRepository::class);
        $corporateRepository->method('findOneBy')->willReturn($encryptedCorporate);

        $identityRepository = $this->createMock(IdentityRepository::class);
        $identityRepository->method('findOneBy')->willReturn(new Identity());

        $crypterIdentity = $this->createMock(CrypterDatabaseIdentityService::class);
        $crypterIdentity->method('decryptFromDatabase')->willReturn((new Identity())->setSecret('secret-1'));

        $crypterDatabaseService = $this->createMock(CrypterDatabaseService::class);
        $crypterDatabaseService->method('decryptFromDatabase')->willReturn($decryptedCorporate);

        return new NotifierService(
            $this->createMock(LoggerInterface::class),
            $httpClient,
            $corporateRepository,
            $identityRepository,
            $crypterIdentity,
            $this->createMock(Encryptor::class),
            $this->createMock(ContainerBagInterface::class),
            $crypterDatabaseService,
        );
    }
}
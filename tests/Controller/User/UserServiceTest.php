<?php

declare(strict_types=1);

namespace App\Tests\Controller\User;

use App\Exception\CorporateRegistrationException;
use App\Helper\AuthorizationHelperFactory;
use App\Service\Shared\AuthorizedEncryptedResponseFactory;
use App\Service\User\UserService;
use App\Service\User\UserAuthorizationResponseFactory;
use App\Service\User\UserQrContentFactory;
use App\DTO\QR\CorporateRegistrationDTO;
use App\DTO\QR\CredentialHubIdentityDTO;
use App\DTO\QR\UserLoginDTO;
use App\Service\AuthBridge\AuthBridgeService;
use App\Service\Crypters\CrypterService;
use App\Service\QrService\QrService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;

final class UserServiceTest extends TestCase
{
    public function testGetQrDataBuildsLoginPayloadAndEncryptedDefaultResponse(): void
    {
        $identity = $this->createIdentity('domainProcessId', 'process-123');

        $qrService = $this->createMock(QrService::class);
        $qrService
            ->expects(self::once())
            ->method('getQrCode')
            ->with(self::callback(function (mixed $dto): bool {
                self::assertInstanceOf(UserLoginDTO::class, $dto);
                self::assertSame('example.test', $dto->domain);
                self::assertSame('process-123', $dto->domainProcessId);
                self::assertSame('auth-one', $dto->xExtensionAuthOne);
                self::assertSame('system_hub_login', $dto->type);
                self::assertSame('corp-1', $dto->corporateId);
                self::assertSame('signature', $dto->corporateAuthentication);
                self::assertSame('corporate', $dto->source);

                return true;
            }))
            ->willReturn('qr-code');

        $service = $this->createUserService($qrService, $identity);

        $result = $service->getQrData([
            'corporatePublicId' => 'corp-1',
            'corporateAuthentication' => 'signature',
            'domain' => 'example.test',
        ], 'domainProcessId');

        self::assertArrayHasKey('defaultResponse', $result);
        self::assertArrayHasKey('mobileResponse', $result);
        self::assertInstanceOf(UserLoginDTO::class, $result['mobileResponse']);
        self::assertSame('process-123', $result['mobileResponse']->domainProcessId);
        self::assertSame('application/json', $result['defaultResponse']['headers']['Content-Type']);
        self::assertArrayHasKey('X-Auth', $result['defaultResponse']['headers']);

        $responseBody = json_decode($result['defaultResponse']['body'], true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('corporateIdentity', $responseBody);
        self::assertArrayHasKey('iv', $responseBody);

        $encryptedPayload = $this->decryptPayload($responseBody['corporateIdentity']);
        self::assertSame('process-123', $encryptedPayload['domainProcessId']);
        self::assertSame('qr-code', $encryptedPayload['qrCode']);
        self::assertSame('auth-one', $encryptedPayload['xExtensionAuthOne']);
    }

    public function testGetQrDataBuildsRegistrationPayloadUsingFirstAuthenticationEntry(): void
    {
        $identity = $this->createIdentity('registrationProcessId', 'registration-123');

        $qrService = $this->createMock(QrService::class);
        $qrService
            ->expects(self::once())
            ->method('getQrCode')
            ->with(self::callback(function (mixed $dto): bool {
                self::assertInstanceOf(CorporateRegistrationDTO::class, $dto);
                self::assertSame('corp-1', $dto->getCorporateId());
                self::assertSame('signature-1', $dto->getCorporateAuthentication());
                self::assertSame('example.test', $dto->getDomain());
                self::assertSame('auth-one', $dto->getXExtensionAuthOne());
                self::assertSame('registration-123', $dto->getRegistrationProcessId());
                self::assertSame('system_hub_registration', $dto->getType());
                self::assertSame('new', $dto->getIsNew());

                return true;
            }))
            ->willReturn('qr-code');

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects(self::once())
            ->method('warning')
            ->with('Registration payload received multiple corporate authentication values; using the first entry.');

        $service = $this->createUserService($qrService, $identity, $logger);

        $result = $service->getQrData([
            'corporatePublicId' => 'corp-1',
            'corporateAuthentication' => ['signature-1', 'signature-2'],
            'domain' => 'example.test',
        ], 'registrationProcessId');

        self::assertInstanceOf(CorporateRegistrationDTO::class, $result['mobileResponse']);
        self::assertSame('signature-1', $result['mobileResponse']->getCorporateAuthentication());

        $responseBody = json_decode($result['defaultResponse']['body'], true, 512, JSON_THROW_ON_ERROR);
        $encryptedPayload = $this->decryptPayload($responseBody['corporateIdentity']);

        self::assertSame('registration-123', $encryptedPayload['registrationProcessId']);
        self::assertSame('qr-code', $encryptedPayload['qrCode']);
    }

    public function testGetQrDataRejectsUnsupportedProcessKey(): void
    {
        $service = $this->createUserService(
            $this->createMock(QrService::class),
            new CredentialHubIdentityDTO(),
        );

        $this->expectException(CorporateRegistrationException::class);
        $this->expectExceptionMessage('Unsupported process key: unsupportedProcessId');

        $service->getQrData(['corporatePublicId' => 'corp-1'], 'unsupportedProcessId');
    }

    private function createUserService(
        QrService $qrService,
        CredentialHubIdentityDTO $identity,
        ?LoggerInterface $logger = null,
    ): UserService {
        $authBridgeService = $this->createMock(AuthBridgeService::class);
        $authBridgeService
            ->expects(self::once())
            ->method('generateRequestIdentity')
            ->willReturn($identity);

        return new UserService(
            $qrService,
            $authBridgeService,
            new UserQrContentFactory($logger ?? $this->createMock(LoggerInterface::class)),
            new UserAuthorizationResponseFactory(
                new AuthorizedEncryptedResponseFactory(
                    new CrypterService($this->createParameterBag()),
                    new AuthorizationHelperFactory(
                        $this->createParameterBag(),
                        $this->createMock(LoggerInterface::class),
                    ),
                ),
            ),
        );
    }

    private function createIdentity(string $processKey, string $processId): CredentialHubIdentityDTO
    {
        $identity = new CredentialHubIdentityDTO();
        $identity->setXExtensionAuthOne('auth-one');

        if ($processKey === 'domainProcessId') {
            $identity->setDomainProcessId($processId);
        }

        if ($processKey === 'registrationProcessId') {
            $identity->setRegistrationProcessId($processId);
        }

        return $identity;
    }

    private function createParameterBag(): ContainerBagInterface&MockObject
    {
        $params = $this->createMock(ContainerBagInterface::class);
        $params
            ->method('get')
            ->willReturnMap([
                ['DATA_HASH_SECRET', '12345678901234567890123456789012'],
                ['SERVICE_API_KEY', 'client-key'],
                ['SERVICE_API_SECRET', 'secret-key'],
            ]);

        return $params;
    }

    /**
     * @return array<string, mixed>
     */
    private function decryptPayload(string $encryptedPayload): array
    {
        $crypter = new CrypterService($this->createParameterBag());
        $crypter->setData($encryptedPayload, false);

        return json_decode($crypter->decryptData(), true, 512, JSON_THROW_ON_ERROR);
    }
}

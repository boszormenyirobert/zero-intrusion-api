<?php

declare(strict_types=1);

namespace App\Tests\Service\AuthBridge\AuthBridgeHandler;

use App\DTO\QR\CredentialHubIdentityDTO;
use App\Entity\AuthBridge;
use App\Repository\AuthBridgeRepository;
use App\Service\AccessRegistry\Database\LoginDatabaseService;
use App\Service\AuthBridge\AuthBridgeHandler\Identity;
use App\Service\Crypters\CrypterDatabaseLoginService;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;

final class IdentityTest extends TestCase
{
    public function testGetBrowserExtensionIdentityBuildsOneTouchIdentityFromStoredProcess(): void
    {
        $capturedSecretData = null;

        $crypter = $this->createMock(CrypterDatabaseLoginService::class);
        $crypter
            ->expects(self::once())
            ->method('encyptExtensionIdentityDataObject')
            ->with(
                self::callback(static function (array $secretData) use (&$capturedSecretData): bool {
                    $capturedSecretData = $secretData;

                    return isset($secretData['secret'], $secretData['oneTouchProcessId']);
                }),
                'oneTouchProcessId'
            )
            ->willReturn((new AuthBridge())->setIv('encoded-iv'));

        $loginDatabase = $this->createMock(LoginDatabaseService::class);
        $loginDatabase
            ->expects(self::once())
            ->method('addUserLogin')
            ->with(self::callback(static function (AuthBridge $authBridge): bool {
                return $authBridge->getOneTouchProcessId() !== null
                    && $authBridge->getTargetId() !== null
                    && $authBridge->isProcessState() === false;
            }))
            ->willReturnCallback(static function (AuthBridge $authBridge): AuthBridge {
                $authBridge->setCreatedAt(new DateTimeImmutable('@1700000000'));

                return $authBridge;
            });

        $service = $this->createService(crypter: $crypter, loginDatabase: $loginDatabase);
        $identity = $service->getBrowserExtensionIdentity('oneTouchProcessId');

        self::assertInstanceOf(CredentialHubIdentityDTO::class, $identity);
        self::assertSame('encoded-iv', $identity->getIv());
        self::assertSame('1700000000', $identity->getCreatedAt());
        self::assertSame($capturedSecretData['oneTouchProcessId'], $identity->oneTouchProcessId);
        self::assertNotEmpty($capturedSecretData['secret']);
    }

    public function testGenerateRequestIdentityAddsExpectedHmacValues(): void
    {
        $capturedSecretData = null;

        $crypter = $this->createMock(CrypterDatabaseLoginService::class);
        $crypter
            ->expects(self::once())
            ->method('encyptExtensionIdentityDataObject')
            ->willReturnCallback(static function (array $secretData) use (&$capturedSecretData): AuthBridge {
                $capturedSecretData = $secretData;

                return (new AuthBridge())->setIv('encoded-iv');
            });

        $loginDatabase = $this->createMock(LoginDatabaseService::class);
        $loginDatabase
            ->expects(self::once())
            ->method('addUserLogin')
            ->willReturnCallback(static function (AuthBridge $authBridge): AuthBridge {
                $authBridge->setCreatedAt(new DateTimeImmutable('@1700000000'));

                return $authBridge;
            });

        $params = $this->createMock(ContainerBagInterface::class);
        $params
            ->method('get')
            ->willReturnMap([
                ['EXTENSION_REGISTRATION_POOL_SECRET', 'shared-secret'],
                ['EXTENSION_REGISTRATION_POOL_MESSAGE', 'shared-message'],
            ]);

        $service = $this->createService(crypter: $crypter, loginDatabase: $loginDatabase, params: $params);
        $identity = $service->generateRequestIdentity('registrationProcessId');

        self::assertSame($capturedSecretData['registrationProcessId'], $identity->registrationProcessId);
        self::assertSame(
            hash_hmac('sha256', 'shared-message|1700000000', 'shared-secret'),
            $identity->getXExtensionAuthOne()
        );
        self::assertSame(
            hash_hmac('sha1', 'shared-message|1700000000', 'shared-secret'),
            $identity->getXExtensionAuthTwo()
        );
    }

    public function testGetBrowserExtensionIdentityStoresRegistrationProcessStateAsFalse(): void
    {
        $crypter = $this->createMock(CrypterDatabaseLoginService::class);
        $crypter
            ->expects(self::once())
            ->method('encyptExtensionIdentityDataObject')
            ->with(self::arrayHasKey('registrationProcessId'), 'registrationProcessId')
            ->willReturn((new AuthBridge())->setIv('encoded-iv'));

        $loginDatabase = $this->createMock(LoginDatabaseService::class);
        $loginDatabase
            ->expects(self::once())
            ->method('addUserLogin')
            ->with(self::callback(static function (AuthBridge $authBridge): bool {
                return $authBridge->getRegistrationProcessId() !== null
                    && $authBridge->isProcessState() === false
                    && $authBridge->getTargetId() !== null;
            }))
            ->willReturnCallback(static function (AuthBridge $authBridge): AuthBridge {
                return $authBridge->setCreatedAt(new DateTimeImmutable('@1700000000'));
            });

        $service = $this->createService(crypter: $crypter, loginDatabase: $loginDatabase);
        $identity = $service->getBrowserExtensionIdentity('registrationProcessId');

        self::assertNotEmpty($identity->registrationProcessId);
    }

    private function createService(
        ?CrypterDatabaseLoginService $crypter = null,
        ?LoginDatabaseService $loginDatabase = null,
        ?ContainerBagInterface $params = null,
    ): Identity {
        return new Identity(
            $crypter ?? $this->createMock(CrypterDatabaseLoginService::class),
            $loginDatabase ?? $this->createMock(LoginDatabaseService::class),
            $params ?? $this->createMock(ContainerBagInterface::class),
            $this->createMock(LoggerInterface::class),
        );
    }
}

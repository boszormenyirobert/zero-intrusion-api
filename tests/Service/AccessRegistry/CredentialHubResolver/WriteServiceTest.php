<?php

declare(strict_types=1);

namespace App\Tests\Service\AccessRegistry\CredentialHubResolver;

use App\Entity\AccessRegistry;
use App\Service\AccessRegistry\CredentialHubResolver\WriteService;
use App\Service\AccessRegistry\Database\CrypterDatabaseAccessRegistryService;
use App\Service\AuthBridge\AuthBridgeHandler\Domain\Encryptor;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;

final class WriteServiceTest extends TestCase
{
    public function testCreateAccessRegistryDomainEncryptsAndPersistsDomainPayload(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager
            ->expects(self::once())
            ->method('persist')
            ->with(self::callback(static function (AccessRegistry $registry): bool {
                return $registry->isRegistrationState() === true
                    && $registry->getPublicId() === 'public-1'
                    && $registry->getRegistrationProcessId() === 'process-1'
                    && $registry->getTargetId() === 'target-1'
                    && $registry->getDomain() !== 'example.com'
                    && $registry->getUserCredential() !== 'secret-credential';
            }));
        $entityManager->expects(self::once())->method('flush');

        $service = $this->createService(entityManager: $entityManager);
        $result = $service->createAccessRegistryDomain([
            'publicId' => 'public-1',
            'registrationProcessId' => 'process-1',
            'targetId' => 'target-1',
            'domain' => 'example.com',
            'userCredential' => 'secret-credential',
            'description' => 'Primary account',
        ], 'domain');

        self::assertTrue($result->isRegistrationState());
        self::assertSame('public-1', $result->getPublicId());
        self::assertSame('process-1', $result->getRegistrationProcessId());
        self::assertSame('target-1', $result->getTargetId());
        self::assertNotSame('example.com', $result->getDomain());
        self::assertNotSame('secret-credential', $result->getUserCredential());
    }

    public function testCreateAccessRegistryDomainEncryptsAndPersistsApplicationPayload(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist');
        $entityManager->expects(self::once())->method('flush');

        $service = $this->createService(entityManager: $entityManager);
        $result = $service->createAccessRegistryDomain([
            'publicId' => 'public-1',
            'registrationProcessId' => 'process-1',
            'targetId' => 'target-1',
            'application' => 'mail',
            'userCredential' => 'secret-credential',
            'description' => 'Primary app',
        ], 'application');

        self::assertTrue($result->isRegistrationState());
        self::assertSame('public-1', $result->getPublicId());
        self::assertSame('process-1', $result->getRegistrationProcessId());
        self::assertSame('target-1', $result->getTargetId());
        self::assertNotSame('mail', $result->getApplication());
        self::assertNotSame('secret-credential', $result->getUserCredential());
    }

    private function createService(?EntityManagerInterface $entityManager = null): WriteService
    {
        return new WriteService(
            $this->createCrypter(),
            $entityManager ?? $this->createMock(EntityManagerInterface::class),
            $this->createMock(LoggerInterface::class),
            $this->createMock(Encryptor::class),
        );
    }

    private function createCrypter(): CrypterDatabaseAccessRegistryService
    {
        $params = $this->createMock(ContainerBagInterface::class);
        $params
            ->method('get')
            ->with('DATABASE_HASH_SECRET')
            ->willReturn('0123456789abcdef0123456789abcdef');

        return new CrypterDatabaseAccessRegistryService(
            $params,
            $this->createMock(LoggerInterface::class),
        );
    }
}

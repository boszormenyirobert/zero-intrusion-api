<?php

declare(strict_types=1);

namespace App\Tests\Controller\CredentialHub\Vault\Read;

use App\Controller\CredentialHub\Vault\Read\VaultReadService;
use App\Entity\AccessRegistry;
use App\Repository\AccessRegistryRepository;
use App\Service\AccessRegistry\Database\CrypterDatabaseAccessRegistryService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;

final class VaultReadServiceTest extends TestCase
{
    public function testGetQrContentBuildsVaultReadDto(): void
    {
        $identity = new class () {
            public function getApplicationProcessId(): string
            {
                return 'application-process-1';
            }

            public function getIv(): string
            {
                return 'iv-1';
            }
        };

        $service = new VaultReadService(
            $this->createMock(AccessRegistryRepository::class),
            $this->createCrypter(),
            $this->createMock(LoggerInterface::class),
        );

        $dto = $service->getQrContent('vault-read', 'extension', 'auth-1', $identity);

        self::assertSame('application-process-1', $dto->applicationProcessId);
        self::assertSame('vault-read', $dto->type);
        self::assertSame('extension', $dto->source);
        self::assertSame('auth-1', $dto->xExtensionAuthOne);
        self::assertSame('iv-1', $dto->iv);
    }

    public function testGetDecryptedCredentialsSkipsRowsWithoutApplication(): void
    {
        $crypter = $this->createCrypter();
        $withApplication = $crypter->encyptDataObject([
            'registrationState' => false,
            'publicId' => 'public-1',
            'registrationProcessId' => 'process-1',
            'targetId' => 'target-1',
            'application' => 'vault-app',
            'userCredential' => 'encrypted-credential',
            'description' => 'description-1',
        ], 'application');
        $withoutApplication = (new AccessRegistry())
            ->setPublicId('public-1')
            ->setTargetId('target-2');

        $repository = $this->createMock(AccessRegistryRepository::class);
        $repository
            ->expects(self::once())
            ->method('findBy')
            ->with(['publicId' => 'public-1'])
            ->willReturn([$withApplication, $withoutApplication]);

        $service = new VaultReadService($repository, $crypter, $this->createMock(LoggerInterface::class));

        self::assertSame([[
            'credential' => 'encrypted-credential',
            'description' => 'description-1',
            'targetId' => 'target-1',
            'application' => 'vault-app',
        ]], $service->getDecryptedCredentials('public-1'));
    }

    private function createCrypter(): CrypterDatabaseAccessRegistryService
    {
        $params = $this->createMock(ContainerBagInterface::class);
        $params
            ->method('get')
            ->with('DATABASE_HASH_SECRET')
            ->willReturn('12345678901234567890123456789012');

        return new CrypterDatabaseAccessRegistryService($params, $this->createMock(LoggerInterface::class));
    }
}
<?php

declare(strict_types=1);

namespace App\Tests\Service\AccessRegistry\CredentialHubResolver;

use App\Repository\AccessRegistryRepository;
use App\Service\AccessRegistry\CredentialHubResolver\CheckService;
use App\Service\AccessRegistry\CredentialHubResolver\DecryptService;
use App\Service\AccessRegistry\CredentialHubResolver\DeleteService;
use App\Service\AccessRegistry\CredentialHubResolver\FilterService;
use App\Service\AccessRegistry\CredentialHubResolver\ResolverService;
use App\Service\AccessRegistry\CredentialHubResolver\WriteService;
use App\Service\AccessRegistry\Database\CrypterDatabaseAccessRegistryService;
use App\Service\AuthBridge\AuthBridgeHandler\Domain\Encryptor;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;

final class ResolverServiceTest extends TestCase
{
    public function testGettersReturnInjectedServices(): void
    {
        $check = new CheckService();
        $decrypt = new DecryptService($this->createCrypter());
        $filter = new FilterService($this->createMock(AccessRegistryRepository::class));
        $write = new WriteService(
            $this->createCrypter(),
            $this->createMock(EntityManagerInterface::class),
            $this->createMock(LoggerInterface::class),
            $this->createMock(Encryptor::class),
        );
        $delete = new DeleteService(
            $this->createMock(AccessRegistryRepository::class),
            $this->createMock(EntityManagerInterface::class),
            $this->createMock(LoggerInterface::class),
        );

        $service = new ResolverService($check, $decrypt, $filter, $write, $delete);

        self::assertSame($check, $service->getCheck());
        self::assertSame($decrypt, $service->getDecrypt());
        self::assertSame($filter, $service->getFilter());
        self::assertSame($write, $service->getWrite());
        self::assertSame($delete, $service->getDelete());
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

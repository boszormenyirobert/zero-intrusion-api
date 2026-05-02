<?php

declare(strict_types=1);

namespace App\Tests\Service\AuthBridge\AuthBridgeHandler\Application;

use App\Entity\AuthBridge;
use App\Repository\AuthBridgeRepository;
use App\Service\AccessRegistry\Database\LoginDatabaseService;
use App\Service\AuthBridge\AuthBridgeHandler\Application\Credential;
use App\Service\AuthBridge\AuthBridgeHandler\Application\Encryptor;
use App\Service\Cache\ProcessStateCacheService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class CredentialTest extends TestCase
{
    public function testSetDecryptedValuesForApplicationReturnsFalseWhenProcessMissing(): void
    {
        $repository = $this->createMock(AuthBridgeRepository::class);
        $repository
            ->expects(self::once())
            ->method('findOneBy')
            ->with(['applicationProcessId' => 'process-123'])
            ->willReturn(null);

        $encryptor = $this->createMock(Encryptor::class);
        $encryptor->expects(self::never())->method('encrypt');

        $cache = $this->createMock(ProcessStateCacheService::class);
        $cache->expects(self::never())->method('set');

        $service = $this->createService(
            repository: $repository,
            encryptor: $encryptor,
            cache: $cache,
        );

        self::assertFalse($service->setDecryptedValuesForApplication([
            'applicationProcessId' => 'process-123',
            'credentials' => [],
        ]));
    }

    public function testSetDecryptedValuesForApplicationEncryptsAndCachesCredentials(): void
    {
        $authBridge = (new AuthBridge())
            ->setApplicationProcessId('process-123')
            ->setIv(base64_encode('iv-value'));

        $repository = $this->createMock(AuthBridgeRepository::class);
        $repository
            ->expects(self::once())
            ->method('findOneBy')
            ->with(['applicationProcessId' => 'process-123'])
            ->willReturn($authBridge);

        $encryptor = $this->createMock(Encryptor::class);
        $encryptor
            ->expects(self::once())
            ->method('encrypt')
            ->with([
                [
                    'decrypted' => 'secret',
                    'description' => 'Mail account',
                    'targetId' => 'target-1',
                    'application' => 'mail',
                ],
            ], 'iv-value')
            ->willReturn('encrypted-payload');

        $cache = $this->createMock(ProcessStateCacheService::class);
        $cache
            ->expects(self::once())
            ->method('set')
            ->with(
                'process-123',
                self::callback(static function (string $payload): bool {
                    $decoded = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);

                    return $decoded['applicationProcessId'] === 'process-123'
                        && $decoded['applications'] === 'encrypted-payload'
                        && $decoded['processState'] === true;
                }),
                300
            );

        $service = $this->createService(
            repository: $repository,
            encryptor: $encryptor,
            cache: $cache,
        );

        self::assertTrue($service->setDecryptedValuesForApplication([
            'applicationProcessId' => 'process-123',
            'credentials' => [
                [
                    'credential' => 'secret',
                    'description' => 'Mail account',
                    'targetId' => 'target-1',
                    'application' => 'mail',
                ],
            ],
        ]));
    }

    public function testProcessToArrayUsesToArrayWhenAvailable(): void
    {
        $service = $this->createService();

        $process = new class {
            public function toArray(): array
            {
                return ['process' => true];
            }
        };

        self::assertSame(['process' => true], $service->processToArray($process));
        self::assertSame([], $service->processToArray(null));
    }

    private function createService(
        ?AuthBridgeRepository $repository = null,
        ?Encryptor $encryptor = null,
        ?ProcessStateCacheService $cache = null,
    ): Credential {
        return new Credential(
            $repository ?? $this->createMock(AuthBridgeRepository::class),
            $this->createMock(LoginDatabaseService::class),
            $this->createMock(LoggerInterface::class),
            $encryptor ?? $this->createMock(Encryptor::class),
            $cache ?? $this->createMock(ProcessStateCacheService::class),
        );
    }
}

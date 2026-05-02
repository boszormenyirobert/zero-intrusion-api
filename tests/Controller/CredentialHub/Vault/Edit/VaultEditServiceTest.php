<?php

declare(strict_types=1);

namespace App\Tests\Controller\CredentialHub\Vault\Edit;

use App\Controller\CredentialHub\Vault\Edit\VaultEditService;
use PHPUnit\Framework\TestCase;

final class VaultEditServiceTest extends TestCase
{
    public function testGetQrContentBuildsVaultEditDto(): void
    {
        $payload = (object) [
            'source' => 'extension',
            'targetId' => 'target-1',
            'type' => 'vault-edit',
            'application' => 'vault-app',
        ];

        $dto = (new VaultEditService())->getQrContent($payload, 'auth-1', 'process-1');

        self::assertSame('extension', $dto->source);
        self::assertSame('target-1', $dto->targetId);
        self::assertSame('vault-edit', $dto->type);
        self::assertSame('auth-1', $dto->xExtensionAuthOne);
        self::assertSame('process-1', $dto->registrationProcessId);
        self::assertSame('vault-app', $dto->application);
    }
}
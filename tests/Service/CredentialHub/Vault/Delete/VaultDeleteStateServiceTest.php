<?php

declare(strict_types=1);

namespace App\Tests\Service\CredentialHub\Vault\Delete;

use App\Service\CredentialHub\SharedPayloadService;
use App\Service\CredentialHub\SharedProcessPoller;
use App\Service\CredentialHub\Vault\Delete\VaultDeleteStateService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class VaultDeleteStateServiceTest extends TestCase
{
    public function testHandleReturnsPolledVaultDeleteState(): void
    {
        $request = Request::create('/api/credential-hub/vault/delete/state', 'POST');

        $sharedPayloadService = $this->createMock(SharedPayloadService::class);
        $sharedPayloadService
            ->expects(self::once())
            ->method('getProcessId')
            ->with($request, 'vault_delete_state')
            ->willReturn('process-1');

        $sharedProcessPoller = $this->createMock(SharedProcessPoller::class);
        $sharedProcessPoller
            ->expects(self::once())
            ->method('pollTheRedisDefault')
            ->with('process-1')
            ->willReturn(['process_check' => true]);

        $service = new VaultDeleteStateService($sharedPayloadService, $sharedProcessPoller);

        self::assertSame(['process_check' => true], $service->handle($request));
    }
}
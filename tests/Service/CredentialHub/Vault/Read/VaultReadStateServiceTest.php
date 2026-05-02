<?php

declare(strict_types=1);

namespace App\Tests\Service\CredentialHub\Vault\Read;

use App\Service\AuthBridge\AuthBridgeService;
use App\Service\CredentialHub\SharedPayloadService;
use App\Service\CredentialHub\SharedProcessPoller;
use App\Service\CredentialHub\Vault\Read\VaultReadStateService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class VaultReadStateServiceTest extends TestCase
{
    public function testHandleReturnsPolledVaultReadState(): void
    {
        $request = Request::create('/api/credential-hub/vault/read/state', 'POST');

        $sharedPayloadService = $this->createMock(SharedPayloadService::class);
        $sharedPayloadService
            ->expects(self::once())
            ->method('getProcessId')
            ->with($request, 'vault_read_state')
            ->willReturn('process-1');

        $authBridgeService = $this->createMock(AuthBridgeService::class);

        $sharedProcessPoller = $this->createMock(SharedProcessPoller::class);
        $sharedProcessPoller
            ->expects(self::once())
            ->method('pollTheRedis')
            ->with('process-1', $authBridgeService, 'application')
            ->willReturn(['process_check' => true, 'applicationList' => []]);

        $service = new VaultReadStateService($sharedPayloadService, $sharedProcessPoller, $authBridgeService);

        self::assertSame(['process_check' => true, 'applicationList' => []], $service->handle($request));
    }
}
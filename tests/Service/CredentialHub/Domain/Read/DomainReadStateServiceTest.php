<?php

declare(strict_types=1);

namespace App\Tests\Service\CredentialHub\Domain\Read;

use App\Service\AuthBridge\AuthBridgeService;
use App\Service\CredentialHub\Domain\Read\DomainReadStateService;
use App\Service\CredentialHub\SharedPayloadService;
use App\Service\CredentialHub\SharedProcessPoller;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class DomainReadStateServiceTest extends TestCase
{
    public function testHandleReturnsPolledDomainPayload(): void
    {
        $request = Request::create('/api/credential-hub/domain/read/state', 'POST');

        $sharedPayloadService = $this->createMock(SharedPayloadService::class);
        $sharedPayloadService
            ->expects(self::once())
            ->method('getProcessId')
            ->with($request, 'domain_read_state')
            ->willReturn('process-1');

        $authBridgeService = $this->createMock(AuthBridgeService::class);

        $sharedProcessPoller = $this->createMock(SharedProcessPoller::class);
        $sharedProcessPoller
            ->expects(self::once())
            ->method('pollTheRedis')
            ->with('process-1', $authBridgeService, 'domain')
            ->willReturn(['process_check' => true, 'domainList' => []]);

        $service = new DomainReadStateService($sharedPayloadService, $sharedProcessPoller, $authBridgeService);

        self::assertSame(['process_check' => true, 'domainList' => []], $service->handle($request));
    }
}
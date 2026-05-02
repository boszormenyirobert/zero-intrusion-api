<?php

declare(strict_types=1);

namespace App\Tests\Service\CredentialHub\Domain\Delete;

use App\Service\CredentialHub\Domain\Delete\DomainDeleteStateService;
use App\Service\CredentialHub\SharedPayloadService;
use App\Service\CredentialHub\SharedProcessPoller;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class DomainDeleteStateServiceTest extends TestCase
{
    public function testHandleReturnsPolledDomainDeleteState(): void
    {
        $request = Request::create('/api/credential-hub/domain/delete/state', 'POST');

        $sharedPayloadService = $this->createMock(SharedPayloadService::class);
        $sharedPayloadService
            ->expects(self::once())
            ->method('getProcessId')
            ->with($request, 'domain_delete_state')
            ->willReturn('process-1');

        $sharedProcessPoller = $this->createMock(SharedProcessPoller::class);
        $sharedProcessPoller
            ->expects(self::once())
            ->method('pollTheRedisDefault')
            ->with('process-1')
            ->willReturn(['process_check' => true]);

        $service = new DomainDeleteStateService($sharedPayloadService, $sharedProcessPoller);

        self::assertSame(['process_check' => true], $service->handle($request));
    }
}
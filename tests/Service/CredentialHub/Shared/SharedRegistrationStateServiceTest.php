<?php

declare(strict_types=1);

namespace App\Tests\Service\CredentialHub\Shared;

use App\Service\CredentialHub\Shared\SharedRegistrationStateService;
use App\Service\CredentialHub\SharedPayloadService;
use App\Service\CredentialHub\SharedProcessPoller;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

final class SharedRegistrationStateServiceTest extends TestCase
{
    public function testHandleReturnsPolledRegistrationState(): void
    {
        $request = Request::create('/api/credential-hub/shared/registration/state', 'POST');

        $sharedPayloadService = $this->createMock(SharedPayloadService::class);
        $sharedPayloadService
            ->expects(self::once())
            ->method('getProcessId')
            ->with($request, 'shared_registration_state')
            ->willReturn('process-1');

        $sharedProcessPoller = $this->createMock(SharedProcessPoller::class);
        $sharedProcessPoller
            ->expects(self::once())
            ->method('pollTheRedisDefault')
            ->with('process-1')
            ->willReturn(['process_check' => true]);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::exactly(2))->method('info');

        $service = new SharedRegistrationStateService($sharedPayloadService, $sharedProcessPoller, $logger);

        self::assertSame(['process_check' => true], $service->handle($request));
    }
}
<?php

declare(strict_types=1);

namespace App\Tests\Service\CredentialHub\OneTouch;

use App\Service\CredentialHub\SharedPayloadService;
use App\Service\CredentialHub\SharedProcessPoller;
use App\Service\CredentialHub\OneTouch\OneTouchStateService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class OneTouchStateServiceTest extends TestCase
{
    public function testHandleReturnsPolledRedisPayload(): void
    {
        $request = Request::create('/api/credential-hub/one-touch/state', 'POST');

        $sharedPayloadService = $this->createMock(SharedPayloadService::class);
        $sharedPayloadService
            ->expects(self::once())
            ->method('getProcessId')
            ->with($request, 'one_touch_state', false)
            ->willReturn('process-1');

        $sharedProcessPoller = $this->createMock(SharedProcessPoller::class);
        $sharedProcessPoller
            ->expects(self::once())
            ->method('pollTheRedisOneTouch')
            ->with('process-1', 'oneTouchProcessId')
            ->willReturn(['success' => true]);

        $service = new OneTouchStateService($sharedPayloadService, $sharedProcessPoller);

        self::assertSame(['success' => true], $service->handle($request));
    }
}

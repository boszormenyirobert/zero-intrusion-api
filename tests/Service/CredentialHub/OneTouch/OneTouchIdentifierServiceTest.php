<?php

declare(strict_types=1);

namespace App\Tests\Service\CredentialHub\OneTouch;

use App\DTO\CredentialHub\OneTouch\OneTouchIdentifierResultDTO;
use App\Service\AuthBridge\AuthBridgeService;
use App\Service\CredentialHub\SharedPayloadService;
use App\Service\CredentialHub\OneTouch\OneTouchIdentifierService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class OneTouchIdentifierServiceTest extends TestCase
{
    public function testHandleReturnsPersistedProcessState(): void
    {
        $request = Request::create('/api/credential-hub/one-touch/identifier', 'POST');
        $process = ['processId' => 'process-1'];

        $sharedPayloadService = $this->createMock(SharedPayloadService::class);
        $sharedPayloadService
            ->expects(self::once())
            ->method('getProcessId')
            ->with($request, 'one_touch_identifier', true)
            ->willReturn($process);

        $authBridgeService = $this->createMock(AuthBridgeService::class);
        $authBridgeService
            ->expects(self::once())
            ->method('persistDecryptedUserData')
            ->with($process)
            ->willReturn(true);

        $service = new OneTouchIdentifierService($sharedPayloadService, $authBridgeService);

        self::assertEquals(new OneTouchIdentifierResultDTO(true, ''), $service->handle($request));
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Service\Device\Restore;

use App\DTO\Device\Restore\ReplaceDeviceRequestDTO;
use App\Service\Device\Restore\ReplaceDeviceService;
use App\Service\Identity\IdentityService;
use App\Service\Restore\RestoreService;
use PHPUnit\Framework\TestCase;

final class ReplaceDeviceServiceTest extends TestCase
{
    public function testHandleReturnsDefaultNotificationsWhenIdentityIsMissing(): void
    {
        $request = new ReplaceDeviceRequestDTO('user@example.test', '+3612345678');

        $identityService = $this->createMock(IdentityService::class);
        $identityService
            ->expects(self::once())
            ->method('getSecret')
            ->with($request->toArray())
            ->willReturn(null);

        $restoreService = $this->createMock(RestoreService::class);
        $restoreService
            ->expects(self::never())
            ->method('recoveryNotification');

        $service = new ReplaceDeviceService($identityService, $restoreService);
        $result = $service->handle($request);

        self::assertSame(false, $result['success']);
        self::assertSame('missing', $result['deviceHash']);
    }

    public function testHandleReturnsRecoveryNotifications(): void
    {
        $request = new ReplaceDeviceRequestDTO('user@example.test', '+3612345678');
        $secretObject = new \stdClass();

        $identityService = $this->createMock(IdentityService::class);
        $identityService
            ->expects(self::once())
            ->method('getSecret')
            ->with($request->toArray())
            ->willReturn($secretObject);

        $restoreService = $this->createMock(RestoreService::class);
        $restoreService
            ->expects(self::once())
            ->method('recoveryNotification')
            ->with($secretObject)
            ->willReturn(['success' => true]);

        $service = new ReplaceDeviceService($identityService, $restoreService);

        self::assertSame(['success' => true], $service->handle($request));
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Service\User\Qr;

use App\Service\User\UserService;
use App\DTO\User\Qr\QrIdentityRequestDTO;
use App\Service\User\Qr\QrIdentityService;
use PHPUnit\Framework\TestCase;

final class QrIdentityServiceTest extends TestCase
{
    public function testHandleReturnsQrIdentityResult(): void
    {
        $request = new QrIdentityRequestDTO(['corporatePublicId' => 'corp-1'], 'registrationProcessId');

        $userService = $this->createMock(UserService::class);
        $userService
            ->expects(self::once())
            ->method('getQrData')
            ->with(['corporatePublicId' => 'corp-1'], 'registrationProcessId')
            ->willReturn([
                'defaultResponse' => [
                    'body' => 'encrypted-body',
                    'headers' => ['X-Test' => 'header'],
                ],
            ]);

        $service = new QrIdentityService($userService);
        $result = $service->handle($request);

        self::assertSame('encrypted-body', $result->body);
        self::assertSame(['X-Test' => 'header'], $result->headers);
    }
}

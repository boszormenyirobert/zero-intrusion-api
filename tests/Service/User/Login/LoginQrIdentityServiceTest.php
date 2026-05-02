<?php

declare(strict_types=1);

namespace App\Tests\Service\User\Login;

use App\Service\User\UserService;
use App\DTO\User\Login\LoginQrIdentityRequestDTO;
use App\Service\Firebase\FirebaseService;
use App\Service\User\Login\LoginQrIdentityService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class LoginQrIdentityServiceTest extends TestCase
{
    public function testHandleTriggersPushNotificationForKnownUser(): void
    {
        $request = new LoginQrIdentityRequestDTO(
            'corp-1',
            'signature',
            'https://example.test',
            'user-1',
        );
        $mobileResponse = (object) ['domainProcessId' => 'process-123'];

        $userService = $this->createMock(UserService::class);
        $userService
            ->expects(self::once())
            ->method('getQrData')
            ->with($request->toPayload(), 'domainProcessId')
            ->willReturn([
                'defaultResponse' => [
                    'body' => 'encrypted-body',
                    'headers' => ['X-Test' => 'header'],
                ],
                'mobileResponse' => $mobileResponse,
            ]);

        $firebaseService = $this->createMock(FirebaseService::class);
        $firebaseService
            ->expects(self::once())
            ->method('manageFcm')
            ->with('user-1', 'Test Title', 'Test Body', $mobileResponse);

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects(self::exactly(2))
            ->method('info');

        $service = new LoginQrIdentityService($userService, $firebaseService, $logger);
        $result = $service->handle($request);

        self::assertSame('encrypted-body', $result->body);
        self::assertSame(['X-Test' => 'header'], $result->headers);
        self::assertSame($mobileResponse, $result->mobileResponse);
    }

    public function testHandleSkipsPushNotificationWithoutUserPublicId(): void
    {
        $request = new LoginQrIdentityRequestDTO(
            'corp-1',
            'signature',
            'https://example.test',
            null,
        );
        $mobileResponse = (object) ['domainProcessId' => 'process-123'];

        $userService = $this->createMock(UserService::class);
        $userService
            ->expects(self::once())
            ->method('getQrData')
            ->with($request->toPayload(), 'domainProcessId')
            ->willReturn([
                'defaultResponse' => [
                    'body' => 'encrypted-body',
                    'headers' => ['X-Test' => 'header'],
                ],
                'mobileResponse' => $mobileResponse,
            ]);

        $firebaseService = $this->createMock(FirebaseService::class);
        $firebaseService
            ->expects(self::never())
            ->method('manageFcm');

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects(self::never())
            ->method('info');

        $service = new LoginQrIdentityService($userService, $firebaseService, $logger);
        $result = $service->handle($request);

        self::assertSame('encrypted-body', $result->body);
        self::assertSame(['X-Test' => 'header'], $result->headers);
    }
}

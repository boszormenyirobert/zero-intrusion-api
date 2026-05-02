<?php

declare(strict_types=1);

namespace App\Tests\Service\User;

use App\Service\Shared\AuthorizedEncryptedResponseFactory;
use App\Service\User\UserAuthorizationResponseFactory;
use PHPUnit\Framework\TestCase;

final class UserAuthorizationResponseFactoryTest extends TestCase
{
    public function testCreateDelegatesToSharedAuthorizedEncryptedResponseFactory(): void
    {
        $sharedFactory = $this->createMock(AuthorizedEncryptedResponseFactory::class);
        $sharedFactory
            ->expects(self::once())
            ->method('create')
            ->with([
                'domainProcessId' => 'process-1',
                'qrCode' => 'qr-code',
            ])
            ->willReturn([
                'headers' => ['X-Test' => 'header'],
                'body' => 'encrypted-body',
            ]);

        $factory = new UserAuthorizationResponseFactory($sharedFactory);

        $result = $factory->create([
            'domainProcessId' => 'process-1',
            'qrCode' => 'qr-code',
        ]);

        self::assertSame(['headers' => ['X-Test' => 'header'], 'body' => 'encrypted-body'], $result);
    }
}
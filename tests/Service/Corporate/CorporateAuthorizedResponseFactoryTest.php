<?php

declare(strict_types=1);

namespace App\Tests\Service\Corporate;

use App\Service\Corporate\CorporateAuthorizedResponseFactory;
use App\Service\Shared\AuthorizedEncryptedResponseFactory;
use PHPUnit\Framework\TestCase;

final class CorporateAuthorizedResponseFactoryTest extends TestCase
{
    public function testCreateDelegatesToSharedAuthorizedEncryptedResponseFactory(): void
    {
        $sharedFactory = $this->createMock(AuthorizedEncryptedResponseFactory::class);
        $sharedFactory
            ->expects(self::once())
            ->method('create')
            ->with([
                'corporate_id' => 'corp-1',
                'scope' => 'internal',
            ])
            ->willReturn([
                'headers' => ['X-Test' => 'header'],
                'body' => 'encrypted-body',
            ]);

        $factory = new CorporateAuthorizedResponseFactory($sharedFactory);

        $result = $factory->create([
            'corporate_id' => 'corp-1',
            'scope' => 'internal',
        ]);

        self::assertSame(['headers' => ['X-Test' => 'header'], 'body' => 'encrypted-body'], $result);
    }
}
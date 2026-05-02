<?php

declare(strict_types=1);

namespace App\Tests\Service\Payload;

use App\Service\Payload\PayloadIntegrityKeyRegistry;
use PHPUnit\Framework\TestCase;

final class PayloadIntegrityKeyRegistryTest extends TestCase
{
    public function testRegistryAcceptsKnownIntegrityKey(): void
    {
        $registry = new PayloadIntegrityKeyRegistry();

        self::assertTrue($registry->isAllowed('business_create'));
    }

    public function testRegistryRejectsUnknownIntegrityKey(): void
    {
        $registry = new PayloadIntegrityKeyRegistry();

        self::assertFalse($registry->isAllowed('not_allowed'));
    }
}

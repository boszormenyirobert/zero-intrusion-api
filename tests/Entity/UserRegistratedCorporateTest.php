<?php

declare(strict_types=1);

namespace App\Tests\Entity;

use App\Entity\UserRegistratedCorporate;
use PHPUnit\Framework\TestCase;

final class UserRegistratedCorporateTest extends TestCase
{
    public function testAccessorsReturnPersistedValues(): void
    {
        $entity = (new UserRegistratedCorporate())
            ->setPublicId('public-1')
            ->setCorporateId('corporate-1');

        self::assertNull($entity->getId());
        self::assertSame('public-1', $entity->getPublicId());
        self::assertSame('corporate-1', $entity->getCorporateId());
    }
}
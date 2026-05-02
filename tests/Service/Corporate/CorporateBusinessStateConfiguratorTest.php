<?php

declare(strict_types=1);

namespace App\Tests\Service\Corporate;

use App\Entity\BusinessServices;
use App\Service\Corporate\CorporateBusinessStateConfigurator;
use PHPUnit\Framework\TestCase;

final class CorporateBusinessStateConfiguratorTest extends TestCase
{
    public function testApplySetsExpectedFlagsForKnownBusinessModel(): void
    {
        $businessServices = new BusinessServices();
        $configurator = new CorporateBusinessStateConfigurator();

        $configurator->apply($businessServices, 'businessPlus');

        self::assertFalse($businessServices->isPro());
        self::assertTrue($businessServices->isPlus());
        self::assertFalse($businessServices->isBasic());
        self::assertFalse($businessServices->isBiometric());
        self::assertFalse($businessServices->isPasswordManager());
    }
}

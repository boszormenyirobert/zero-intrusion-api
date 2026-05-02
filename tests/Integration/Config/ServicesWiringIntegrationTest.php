<?php

declare(strict_types=1);

namespace App\Tests\Integration\Config;

use App\Kernel;
use App\Service\Corporate\CorporateAuthorizedResponseFactory;
use App\Service\Corporate\CorporateRegistrationService;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class ServicesWiringIntegrationTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    public function testCorporateServicesAreResolvedThroughContainerAutowiring(): void
    {
        self::bootKernel();

        $container = static::getContainer();

        self::assertInstanceOf(CorporateRegistrationService::class, $container->get(CorporateRegistrationService::class));
        self::assertInstanceOf(CorporateAuthorizedResponseFactory::class, $container->get(CorporateAuthorizedResponseFactory::class));
    }
}
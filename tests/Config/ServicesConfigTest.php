<?php

declare(strict_types=1);

namespace App\Tests\Config;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

final class ServicesConfigTest extends TestCase
{
    public function testServicesConfigDoesNotUseLegacyPercentParenthesisParameterSyntax(): void
    {
        $contents = (string) file_get_contents(__DIR__ . '/../../config/services.yaml');

        self::assertStringNotContainsString('%(', $contents);
    }

    public function testCorporateRegistrationServiceDoesNotUseLegacyExplicitScalarArgumentMap(): void
    {
        $config = Yaml::parseFile(__DIR__ . '/../../config/services.yaml');

        self::assertArrayNotHasKey(
            'App\\Service\\Corporate\\CorporateRegistrationService',
            $config['services'] ?? []
        );
    }
}
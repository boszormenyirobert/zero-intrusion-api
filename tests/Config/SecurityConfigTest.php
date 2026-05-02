<?php

declare(strict_types=1);

namespace App\Tests\Config;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Yaml\Yaml;

final class SecurityConfigTest extends TestCase
{
    public function testLegacyMainFirewallIsAbsent(): void
    {
        $config = Yaml::parseFile(__DIR__ . '/../../config/packages/security.yaml');

        self::assertArrayNotHasKey('main', $config['security']['firewalls'] ?? []);
    }

    public function testLegacyInMemoryProviderIsAbsent(): void
    {
        $config = Yaml::parseFile(__DIR__ . '/../../config/packages/security.yaml');

        self::assertArrayNotHasKey('users_in_memory', $config['security']['providers'] ?? []);
    }

    public function testLegacyPublicAccessRulesAreAbsentForApiEndpoints(): void
    {
        $config = Yaml::parseFile(__DIR__ . '/../../config/packages/security.yaml');

        self::assertArrayNotHasKey('access_control', $config['security'] ?? []);
    }

    public function testPasswordAuthenticatedInterfaceHasherIsConfigured(): void
    {
        $config = Yaml::parseFile(__DIR__ . '/../../config/packages/security.yaml');

        self::assertSame(
            'auto',
            $config['security']['password_hashers']['Symfony\\Component\\Security\\Core\\User\\PasswordAuthenticatedUserInterface'] ?? null
        );
    }

    public function testApplicationSpecificUserHasherIsAbsentWhenUserEntityIsMissing(): void
    {
        $config = Yaml::parseFile(__DIR__ . '/../../config/packages/security.yaml');

        self::assertArrayNotHasKey('App\\Entity\\User', $config['security']['password_hashers'] ?? []);
    }

    public function testTestEnvironmentDoesNotIntroduceMissingUserEntityHasher(): void
    {
        $config = Yaml::parseFile(__DIR__ . '/../../config/packages/security.yaml');

        self::assertArrayNotHasKey('App\\Entity\\User', $config['when@test']['security']['password_hashers'] ?? []);
    }

    public function testTestEnvironmentStillConfiguresGenericPasswordHasher(): void
    {
        $config = Yaml::parseFile(__DIR__ . '/../../config/packages/security.yaml');

        self::assertSame(
            'auto',
            $config['when@test']['security']['password_hashers']['Symfony\\Component\\Security\\Core\\User\\PasswordAuthenticatedUserInterface']['algorithm'] ?? null,
        );
    }
}
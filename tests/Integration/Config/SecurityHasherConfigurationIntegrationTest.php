<?php

declare(strict_types=1);

namespace App\Tests\Integration\Config;

use App\Kernel;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;

final class SecurityHasherConfigurationIntegrationTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    public function testPasswordHasherFactoryBuildsHasherForPasswordAuthenticatedUserInterface(): void
    {
        self::bootKernel();

        $factory = static::getContainer()->get(PasswordHasherFactoryInterface::class);
        $user = new class implements PasswordAuthenticatedUserInterface {
            public function getPassword(): ?string
            {
                return null;
            }
        };

        $hasher = $factory->getPasswordHasher($user);
        $hash = $hasher->hash('plain-password');

        self::assertNotSame('', $hash);
        self::assertTrue($hasher->verify($hash, 'plain-password'));
    }
}
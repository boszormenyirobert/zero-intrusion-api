<?php

declare(strict_types=1);

namespace App\Tests\Service\AccessRegistry\CredentialHubResolver;

use App\Entity\AccessRegistry;
use App\Service\AccessRegistry\CredentialHubResolver\CheckService;
use PHPUnit\Framework\TestCase;

final class CheckServiceTest extends TestCase
{
    public function testUserDomainCombinationExistsAllowsMultipleDomainCredentials(): void
    {
        $service = new CheckService();

        $page = (new AccessRegistry())
            ->setPublicId('public-1')
            ->setDomain('example.com');

        self::assertSame(
            ['newCombination' => true, 'existingPage' => []],
            $service->userDomainCombinationExists(
                ['publicId' => 'public-1', 'domain' => 'example.com'],
                [$page],
                'domain'
            )
        );
    }

    public function testUserDomainCombinationExistsRejectsDuplicateApplication(): void
    {
        $service = new CheckService();

        $page = (new AccessRegistry())
            ->setPublicId('public-1')
            ->setApplication('mail');

        self::assertSame(
            ['newCombination' => false, 'existingPage' => []],
            $service->userDomainCombinationExists(
                ['publicId' => 'public-1', 'application' => 'mail'],
                [$page],
                'application'
            )
        );
    }

    public function testGetUserDomainCombinationReturnsMatchingPage(): void
    {
        $service = new CheckService();

        $match = (new AccessRegistry())
            ->setPublicId('public-1')
            ->setDomain('example.com')
            ->setTargetId('target-1');

        $other = (new AccessRegistry())
            ->setPublicId('public-2')
            ->setDomain('other.com')
            ->setTargetId('target-2');

        self::assertSame(
            $match,
            $service->getUserDomainCombination(
                ['publicId' => 'public-1', 'domain' => 'example.com', 'targetId' => 'target-1'],
                [$other, $match]
            )
        );
    }
}

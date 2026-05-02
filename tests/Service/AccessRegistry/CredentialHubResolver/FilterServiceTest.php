<?php

declare(strict_types=1);

namespace App\Tests\Service\AccessRegistry\CredentialHubResolver;

use App\Entity\AccessRegistry;
use App\Repository\AccessRegistryRepository;
use App\Service\AccessRegistry\CredentialHubResolver\FilterService;
use PHPUnit\Framework\TestCase;

final class FilterServiceTest extends TestCase
{
    public function testGetUserRegistratedPagesReturnsEmptyArrayWhenRepositoryHasNoEntries(): void
    {
        $repository = $this->createMock(AccessRegistryRepository::class);
        $repository
            ->expects(self::once())
            ->method('findBy')
            ->with(['publicId' => 'public-1'])
            ->willReturn([]);

        $service = new FilterService($repository);

        self::assertSame([], $service->getUserRegistratedPages(['publicId' => 'public-1'], 'domain'));
    }

    public function testGetUserRegistratedPagesReturnsOnlyApplicationEntries(): void
    {
        $application = (new AccessRegistry())
            ->setPublicId('public-1')
            ->setApplication('mail');
        $domain = (new AccessRegistry())
            ->setPublicId('public-1')
            ->setDomain('example.com');

        $repository = $this->createMock(AccessRegistryRepository::class);
        $repository
            ->expects(self::once())
            ->method('findBy')
            ->with(['publicId' => 'public-1'])
            ->willReturn([$application, $domain]);

        $service = new FilterService($repository);

        self::assertSame([$application], array_values($service->getUserRegistratedPages(['publicId' => 'public-1'], 'application')));
    }

    public function testGetUserRegistratedPagesReturnsOnlyDomainEntries(): void
    {
        $application = (new AccessRegistry())
            ->setPublicId('public-1')
            ->setApplication('mail');
        $domain = (new AccessRegistry())
            ->setPublicId('public-1')
            ->setDomain('example.com');

        $repository = $this->createMock(AccessRegistryRepository::class);
        $repository
            ->expects(self::once())
            ->method('findBy')
            ->with(['publicId' => 'public-1'])
            ->willReturn([$application, $domain]);

        $service = new FilterService($repository);

        self::assertSame([$domain], array_values($service->getUserRegistratedPages(['publicId' => 'public-1'], 'domain')));
    }
}

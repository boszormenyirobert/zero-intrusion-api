<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller;

use App\Kernel;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class EmptyControllerIntegrationTest extends WebTestCase
{
    protected static function getKernelClass(): string
    {
        return Kernel::class;
    }

    public function testRootEndpointReturnsHealthResponse(): void
    {
        $client = static::createClient();

        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSame('Healty ok', (string) $client->getResponse()->getContent());
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Controller\EmptyController;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

final class EmptyControllerTest extends TestCase
{
    public function testEmptyRouteReturnsPlainHealthResponse(): void
    {
        $controller = new EmptyController();

        $response = $controller->empty();

        self::assertInstanceOf(Response::class, $response);
        self::assertSame(200, $response->getStatusCode());
        self::assertSame('Healty ok', $response->getContent());
    }
}

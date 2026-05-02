<?php

declare(strict_types=1);

namespace App\Tests\Service\Payload;

use App\Service\Payload\ValidatedPayloadResolver;
use App\Service\Shared\RequestService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;

final class ValidatedPayloadResolverTest extends TestCase
{
    public function testResolveReturnsValidatedPayloadFromRequestAttribute(): void
    {
        $request = new Request();
        $request->attributes = new ParameterBag([
            'json_payload' => ['zeroIntrusionProyApi' => 'encrypted'],
        ]);

        $requestService = $this->createMock(RequestService::class);
        $requestService
            ->expects(self::once())
            ->method('validPayload')
            ->with(['zeroIntrusionProyApi' => 'encrypted'])
            ->willReturn(['business_create' => 'value']);

        $resolver = new ValidatedPayloadResolver($requestService);

        self::assertSame(['business_create' => 'value'], $resolver->resolve($request));
    }
}

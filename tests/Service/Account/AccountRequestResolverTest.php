<?php

declare(strict_types=1);

namespace App\Tests\Service\Account;

use App\Service\Account\AccountRequestResolver;
use App\Service\Shared\RequestService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class AccountRequestResolverTest extends TestCase
{
    public function testResolveReturnsValidatedPayload(): void
    {
        $request = Request::create('/api/account/all', 'POST');
        $rawPayload = ['zeroIntrusionProyApi' => 'encrypted'];
        $validatedPayload = ['get_registrated_business' => ['publicId' => 'public-1']];

        $requestService = $this->createMock(RequestService::class);
        $requestService
            ->expects(self::once())
            ->method('validateRequest')
            ->with($request)
            ->willReturn($rawPayload);
        $requestService
            ->expects(self::once())
            ->method('validPayload')
            ->with($rawPayload)
            ->willReturn($validatedPayload);

        $resolver = new AccountRequestResolver($requestService);

        self::assertSame($validatedPayload, $resolver->resolve($request));
    }

    public function testResolveRejectsErrorPayload(): void
    {
        $request = Request::create('/api/account/all', 'POST');
        $requestService = $this->createMock(RequestService::class);
        $requestService
            ->expects(self::once())
            ->method('validateRequest')
            ->with($request)
            ->willReturn(['error' => 'Invalid HMAC signature']);
        $requestService
            ->expects(self::never())
            ->method('validPayload');

        $resolver = new AccountRequestResolver($requestService);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid account payload.');

        $resolver->resolve($request);
    }
}

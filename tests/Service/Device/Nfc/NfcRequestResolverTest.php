<?php

declare(strict_types=1);

namespace App\Tests\Service\Device\Nfc;

use App\Service\Device\Nfc\NfcRequestResolver;
use App\Service\Shared\RequestService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class NfcRequestResolverTest extends TestCase
{
    public function testResolveReturnsValidatedPayload(): void
    {
        $request = Request::create('/api/nfc/decrypt', 'POST');
        $rawPayload = ['zeroIntrusionProyApi' => 'encrypted'];
        $validatedPayload = ['api_nfc_decrypt' => ['userPublicId' => 'public-1']];

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

        $resolver = new NfcRequestResolver($requestService);

        self::assertSame($validatedPayload, $resolver->resolve($request));
    }

    public function testResolveRejectsErrorPayload(): void
    {
        $request = Request::create('/api/nfc/decrypt', 'POST');
        $requestService = $this->createMock(RequestService::class);
        $requestService
            ->expects(self::once())
            ->method('validateRequest')
            ->with($request)
            ->willReturn(['error' => 'Invalid HMAC signature']);
        $requestService
            ->expects(self::never())
            ->method('validPayload');

        $resolver = new NfcRequestResolver($requestService);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid NFC payload.');

        $resolver->resolve($request);
    }
}

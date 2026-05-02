<?php

declare(strict_types=1);

namespace App\Tests\Service\Request;

use App\Service\Request\JsonRequestEnvelopeValidator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class JsonRequestEnvelopeValidatorTest extends TestCase
{
    public function testValidateReturnsDecodedJsonPayload(): void
    {
        $validator = new JsonRequestEnvelopeValidator();
        $request = Request::create(
            '/api/account/all',
            'POST',
            [],
            [],
            [],
            [],
            json_encode(['zeroIntrusionProyApi' => 'encrypted', 'iv' => 'iv-value'], JSON_THROW_ON_ERROR)
        );

        self::assertSame([
            'zeroIntrusionProyApi' => 'encrypted',
            'iv' => 'iv-value',
        ], $validator->validate($request));
    }

    public function testValidateReturnsErrorPayloadForInvalidJson(): void
    {
        $validator = new JsonRequestEnvelopeValidator();
        $request = Request::create('/api/account/all', 'POST', [], [], [], [], '{invalid');

        self::assertSame(['error' => 'Invalid JSON payload'], $validator->validate($request));
    }
}

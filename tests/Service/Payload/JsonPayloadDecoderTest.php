<?php

declare(strict_types=1);

namespace App\Tests\Service\Payload;

use App\Service\Payload\JsonPayloadDecoder;
use PHPUnit\Framework\TestCase;

final class JsonPayloadDecoderTest extends TestCase
{
    public function testDecodeArrayReturnsDecodedJsonArray(): void
    {
        $decoder = new JsonPayloadDecoder();

        self::assertSame(['key' => 'value'], $decoder->decodeArray('{"key":"value"}'));
    }

    public function testDecodeArrayReturnsOriginalArrayInput(): void
    {
        $decoder = new JsonPayloadDecoder();

        self::assertSame(['key' => 'value'], $decoder->decodeArray(['key' => 'value']));
    }

    public function testDecodeArrayReturnsNullForInvalidJson(): void
    {
        $decoder = new JsonPayloadDecoder();

        self::assertNull($decoder->decodeArray('{invalid-json'));
    }

    public function testDecodeArrayReturnsNullForEmptyOrNonArrayPayloads(): void
    {
        $decoder = new JsonPayloadDecoder();

        self::assertNull($decoder->decodeArray(''));
        self::assertNull($decoder->decodeArray('"scalar"'));
        self::assertNull($decoder->decodeArray(null));
    }

    public function testRequireArrayThrowsForInvalidPayload(): void
    {
        $decoder = new JsonPayloadDecoder();

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid payload.');

        $decoder->requireArray('{invalid-json', 'Invalid payload.');
    }

    public function testRequireStringArrayDistinguishesInvalidAndNonArrayPayloads(): void
    {
        $decoder = new JsonPayloadDecoder();

        try {
            $decoder->requireStringArray('{invalid-json', 'Invalid payload.', 'Not an array payload.');
            self::fail('Expected InvalidArgumentException was not thrown for invalid JSON.');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame('Invalid payload.', $exception->getMessage());
        }

        try {
            $decoder->requireStringArray('"scalar"', 'Invalid payload.', 'Not an array payload.');
            self::fail('Expected InvalidArgumentException was not thrown for non-array JSON.');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame('Not an array payload.', $exception->getMessage());
        }
    }
}
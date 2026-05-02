<?php

declare(strict_types=1);

namespace App\Tests\Logger;

use App\Logger\RequestIdProcessor;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class RequestIdProcessorTest extends TestCase
{
    public function testInvokeReturnsOriginalRecordWhenNoCurrentRequestExists(): void
    {
        $processor = new RequestIdProcessor(new RequestStack());
        $record = ['message' => 'test', 'extra' => []];

        self::assertSame($record, $processor($record));
    }

    public function testInvokeUsesHeaderRequestIdWhenPresent(): void
    {
        $request = Request::create('/api/test');
        $request->headers->set('X-Request-Id', 'request-123');

        $stack = new RequestStack();
        $stack->push($request);

        $processor = new RequestIdProcessor($stack);
        $record = ['message' => 'test', 'extra' => []];

        self::assertSame('request-123', $processor($record)['extra']['request_id']);
    }

    public function testInvokeGeneratesFallbackRequestIdWhenHeaderMissing(): void
    {
        $stack = new RequestStack();
        $stack->push(Request::create('/api/test'));

        $processor = new RequestIdProcessor($stack);
        $processed = $processor(['message' => 'test', 'extra' => []]);

        self::assertMatchesRegularExpression('/^[a-f0-9]{16}$/', $processed['extra']['request_id']);
    }
}
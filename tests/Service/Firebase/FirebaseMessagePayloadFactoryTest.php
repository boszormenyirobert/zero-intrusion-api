<?php

declare(strict_types=1);

namespace App\Tests\Service\Firebase;

use App\Service\Firebase\FirebaseMessagePayloadFactory;
use PHPUnit\Framework\TestCase;

final class FirebaseMessagePayloadFactoryTest extends TestCase
{
    public function testCreateBuildsExpectedFirebasePayload(): void
    {
        $factory = new FirebaseMessagePayloadFactory();

        $payload = $factory->create('device-token', 'Title', 'Body', (object) ['domainProcessId' => 'process-1']);

        self::assertSame('device-token', $payload['message']['token']);
        self::assertSame('HIGH', $payload['message']['android']['priority']);
        self::assertSame('Title', $payload['message']['data']['title']);
        self::assertSame('Body', $payload['message']['data']['body']);
        self::assertSame('{"domainProcessId":"process-1"}', $payload['message']['data']['qrData']);
    }
}

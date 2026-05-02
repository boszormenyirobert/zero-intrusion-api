<?php

declare(strict_types=1);

namespace App\Tests\Service\Hmac;

use App\Entity\AuthBridge;
use App\Service\Hmac\ListenerHmacPolicy;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

final class ListenerHmacPolicyTest extends TestCase
{
    public function testValidatePoolHeaderAcceptsMatchingSha256Signature(): void
    {
        $process = (new AuthBridge())->setCreatedAt(new \DateTimeImmutable());
        $timestamp = $process->getCreatedAt()?->getTimestamp();

        self::assertIsInt($timestamp);

        $policy = new ListenerHmacPolicy($this->createParameterBag(), $this->createMock(LoggerInterface::class));

        self::assertTrue($policy->validatePoolHeader(
            'HMAC ' . hash_hmac('sha256', 'pool-message|' . $timestamp, 'pool-secret'),
            $process,
            'sha256'
        ));
    }

    public function testValidatePoolHeaderRejectsExpiredProcessTimestamp(): void
    {
        $process = (new AuthBridge())->setCreatedAt(new \DateTimeImmutable('-20 seconds'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error')->with('Time difference too large.');

        $policy = new ListenerHmacPolicy($this->createParameterBag(), $logger);

        self::assertFalse($policy->validatePoolHeader('HMAC anything', $process, 'sha256'));
    }

    public function testValidatePoolHeaderRejectsInvalidHeaderFormat(): void
    {
        $process = (new AuthBridge())->setCreatedAt(new \DateTimeImmutable());

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error')->with('Invalid HMAC header format.');

        $policy = new ListenerHmacPolicy($this->createParameterBag(), $logger);

        self::assertFalse($policy->validatePoolHeader('Bearer invalid', $process, 'sha1'));
    }

    private function createParameterBag(): ParameterBagInterface&MockObject
    {
        $params = $this->createMock(ParameterBagInterface::class);
        $params
            ->method('get')
            ->willReturnMap([
                ['EXTENSION_REGISTRATION_POOL_SECRET', 'pool-secret'],
                ['EXTENSION_REGISTRATION_POOL_MESSAGE', 'pool-message'],
            ]);

        return $params;
    }
}
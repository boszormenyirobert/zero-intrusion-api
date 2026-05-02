<?php

declare(strict_types=1);

namespace App\Tests\Service\Hmac;

use App\DTO\Hmac\ListenerRoutePayloadResolutionDTO;
use App\Service\Crypters\CrypterService;
use App\Service\Hmac\ListenerPayloadResolver;
use App\Service\Payload\JsonPayloadDecoder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;

final class ListenerPayloadResolverTest extends TestCase
{
    public function testDecodeRequestPayloadReturnsDecodedEnvelope(): void
    {
        $resolver = $this->createResolver();

        self::assertSame([
            'iv' => 'iv-value',
            'zeroIntrusionProyApi' => 'payload',
        ], $resolver->decodeRequestPayload('{"iv":"iv-value","zeroIntrusionProyApi":"payload"}'));
    }

    public function testHasEncryptedEnvelopeRequiresIvAndPayload(): void
    {
        $resolver = $this->createResolver();

        self::assertTrue($resolver->hasEncryptedEnvelope(['iv' => 'iv-value', 'zeroIntrusionProyApi' => 'payload']));
        self::assertFalse($resolver->hasEncryptedEnvelope(['iv' => 'iv-value']));
    }

    public function testDecodeEncryptedPayloadReturnsDecodedArray(): void
    {
        $resolver = $this->createResolver();
        $crypter = new CrypterService($this->createParameterBag());

        self::assertSame(
            ['domain_read_state' => ['processId' => 'process-1']],
            $resolver->decodeEncryptedPayload([
                'zeroIntrusionProyApi' => $crypter->encrypt(['domain_read_state' => ['processId' => 'process-1']]),
            ])
        );
    }

    public function testResolveRoutePayloadHandlesArrayAndJsonStringInputs(): void
    {
        $resolver = $this->createResolver();

        self::assertSame(['processId' => 'process-1'], $resolver->resolveRoutePayload([
            'domain_read_state' => ['processId' => 'process-1'],
        ], 'domain_read_state'));

        self::assertSame(['processId' => 'process-1'], $resolver->resolveRoutePayload([
            'domain_read_state' => '{"processId":"process-1"}',
        ], 'domain_read_state'));
    }

    public function testResolveRoutePayloadReturnsNullForMissingOrInvalidPayload(): void
    {
        $resolver = $this->createResolver();

        self::assertNull($resolver->resolveRoutePayload([], 'domain_read_state'));
        self::assertNull($resolver->resolveRoutePayload(['domain_read_state' => '"scalar"'], 'domain_read_state'));
    }

    public function testResolveDecryptedRoutePayloadMarksMissingPayloadKey(): void
    {
        $resolver = $this->createResolver();

        self::assertEquals(
            new ListenerRoutePayloadResolutionDTO(null, false, true),
            $resolver->resolveDecryptedRoutePayload([], 'domain_read_state')
        );
    }

    public function testResolveDecryptedRoutePayloadMarksInvalidInnerPayload(): void
    {
        $resolver = $this->createResolver();

        self::assertEquals(
            new ListenerRoutePayloadResolutionDTO(null, true, false),
            $resolver->resolveDecryptedRoutePayload(['domain_read_state' => '"scalar"'], 'domain_read_state')
        );
    }

    private function createResolver(): ListenerPayloadResolver
    {
        return new ListenerPayloadResolver(new JsonPayloadDecoder(), new CrypterService($this->createParameterBag()));
    }

    private function createParameterBag(): ContainerBagInterface&MockObject
    {
        $params = $this->createMock(ContainerBagInterface::class);
        $params
            ->method('get')
            ->willReturnMap([
                ['DATA_HASH_SECRET', '12345678901234567890123456789012'],
            ]);

        return $params;
    }
}
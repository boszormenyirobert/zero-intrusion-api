<?php

declare(strict_types=1);

namespace App\Tests\Service\Payload;

use App\Exception\MissingKeyException;
use App\Service\Payload\PayloadIntegrityKeyRegistry;
use App\Service\Payload\PayloadValidator;
use App\Service\Payload\ValidatedPayloadResolver;
use App\Service\Shared\RequestService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\HttpFoundation\Request;

final class PayloadValidatorTest extends TestCase
{
    public function testValidatePayloadReturnsValidatedPayloadForAllowedKey(): void
    {
        $payload = ['business_create' => 'value'];
        $request = $this->createRequestWithPayload(['zeroIntrusionProyApi' => 'encrypted']);

        $requestService = $this->createMock(RequestService::class);
        $requestService
            ->expects(self::once())
            ->method('validPayload')
            ->with(['zeroIntrusionProyApi' => 'encrypted'])
            ->willReturn($payload);

        $validator = new PayloadValidator(
            $this->createMock(LoggerInterface::class),
            new ValidatedPayloadResolver($requestService),
            new PayloadIntegrityKeyRegistry()
        );

        self::assertSame($payload, $validator->validatePayload($request, 'business_create'));
    }

    public function testValidatePayloadThrowsForUnauthorizedIntegrityKey(): void
    {
        $request = $this->createRequestWithPayload(['zeroIntrusionProyApi' => 'encrypted']);

        $requestService = $this->createMock(RequestService::class);
        $requestService
            ->expects(self::once())
            ->method('validPayload')
            ->with(['zeroIntrusionProyApi' => 'encrypted'])
            ->willReturn(['business_create' => 'value']);

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects(self::once())
            ->method('critical')
            ->with('not_allowed is not whitelisted');

        $validator = new PayloadValidator($logger, new ValidatedPayloadResolver($requestService), new PayloadIntegrityKeyRegistry());

        $this->expectException(MissingKeyException::class);
        $this->expectExceptionMessage('Not authorized integrity key: not_allowed');

        $validator->validatePayload($request, 'not_allowed');
    }

    public function testGetValidatedPayloadThrowsForMissingRequiredKey(): void
    {
        $request = $this->createRequestWithPayload(['zeroIntrusionProyApi' => 'encrypted']);

        $requestService = $this->createMock(RequestService::class);
        $requestService
            ->expects(self::once())
            ->method('validPayload')
            ->with(['zeroIntrusionProyApi' => 'encrypted'])
            ->willReturn(['other' => 'value']);

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects(self::once())
            ->method('critical')
            ->with('Property "business_create" missing');
        $logger
            ->expects(self::once())
            ->method('error')
            ->with(
                'Payload validation failed',
                [
                    'error' => 'Property "business_create" missing',
                    'payload' => ['zeroIntrusionProyApi' => 'encrypted'],
                ]
            );

        $validator = new PayloadValidator($logger, new ValidatedPayloadResolver($requestService), new PayloadIntegrityKeyRegistry());

        $this->expectException(MissingKeyException::class);
        $this->expectExceptionMessage('Property "business_create" missing');

        $validator->getValidatedPayload($request, 'business_create');
    }

    private function createRequestWithPayload(array $payload): Request
    {
        $request = new Request();
        $request->attributes = new ParameterBag(['json_payload' => $payload]);

        return $request;
    }
}

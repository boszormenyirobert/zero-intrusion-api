<?php

declare(strict_types=1);

namespace App\Tests\Service\CredentialHub;

use App\Controller\PayloadValidator\PayloadValidator;
use App\Service\CredentialHub\SharedPayloadService;
use App\Service\Payload\JsonPayloadDecoder;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

final class SharedPayloadServiceAdditionalTest extends TestCase
{
    public function testDecodeJsonRejectsMissingWrongTypeEmptyAndNonArrayPayloads(): void
    {
        $service = new SharedPayloadService($this->createMock(PayloadValidator::class), $this->createMock(LoggerInterface::class), new JsonPayloadDecoder());

        foreach ([
            [[], "Payload key 'payload' is missing."],
            [['payload' => ['not-string']], "Payload key 'payload' must be a JSON string."],
            [['payload' => ''], "Payload key 'payload' cannot be empty."],
            [['payload' => '"scalar"'], 'Payload key "payload" must decode to an array.'],
        ] as [$payload, $message]) {
            try {
                $service->decodeJson($payload, 'payload');
                self::fail('Expected InvalidArgumentException was not thrown.');
            } catch (\InvalidArgumentException $exception) {
                self::assertSame($message, $exception->getMessage());
            }
        }
    }

    public function testGetApplicationDtoAndFullPayloadExtractionReturnExpectedValues(): void
    {
        $request = Request::create('/api/credential-hub/vault/delete/state', 'POST');
        $payloadValidator = $this->createMock(PayloadValidator::class);
        $payloadValidator
            ->expects(self::once())
            ->method('validatePayload')
            ->with($request, 'vault_delete_state')
            ->willReturn([
                'vault_delete_state' => '{"processId":"process-1","targetId":"target-1","removeProcessId":"remove-1"}',
            ]);

        $service = new SharedPayloadService($payloadValidator, $this->createMock(LoggerInterface::class), new JsonPayloadDecoder());
        $payload = $service->getProcessId($request, 'vault_delete_state', true);

        self::assertSame([
            'processId' => 'process-1',
            'targetId' => 'target-1',
            'removeProcessId' => 'remove-1',
        ], $payload);

        $dto = $service->getApplicationDto([
            'removeProcessId' => 'remove-1',
            'targetId' => 'target-1',
        ]);

        self::assertSame('remove-1', $dto->removeProcessId);
        self::assertSame('target-1', $dto->targetId);
    }

    public function testGetPayloadOrFailRejectsInvalidAndEmptyJsonString(): void
    {
        $request = Request::create('/api/test', 'POST');
        $payloadValidator = $this->createMock(PayloadValidator::class);
        $payloadValidator
            ->expects(self::exactly(2))
            ->method('validatePayload')
            ->withConsecutive([$request, 'payload'], [$request, 'payload'])
            ->willReturnOnConsecutiveCalls(['payload' => '{invalid'], ['payload' => '']);

        $service = new SharedPayloadService($payloadValidator, $this->createMock(LoggerInterface::class), new JsonPayloadDecoder());

        try {
            $service->getPayloadOrFail($request, 'payload');
            self::fail('Expected InvalidArgumentException was not thrown for invalid JSON.');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame('Payload key "payload" must contain valid JSON.', $exception->getMessage());
        }

        try {
            $service->getPayloadOrFail($request, 'payload');
            self::fail('Expected InvalidArgumentException was not thrown for empty payload.');
        } catch (\InvalidArgumentException $exception) {
            self::assertSame("Payload key 'payload' cannot be empty.", $exception->getMessage());
        }
    }

    public function testGetPayloadReturnsEmptyArrayForInvalidOrEmptyJsonString(): void
    {
        $request = Request::create('/api/test', 'POST');
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('warning')->with('CredentialHub shared payload could not be decoded.', ['payload_key' => 'payload']);

        $payloadValidator = $this->createMock(PayloadValidator::class);
        $payloadValidator
            ->expects(self::exactly(2))
            ->method('validatePayload')
            ->withConsecutive([$request, 'payload'], [$request, 'payload'])
            ->willReturnOnConsecutiveCalls(['payload' => '{invalid'], ['payload' => '']);

        $service = new SharedPayloadService($payloadValidator, $logger, new JsonPayloadDecoder());

        self::assertSame([], $service->getPayload($request, 'payload'));
        self::assertSame([], $service->getPayload($request, 'payload'));
    }
}
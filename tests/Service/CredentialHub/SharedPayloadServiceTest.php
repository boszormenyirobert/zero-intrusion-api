<?php

declare(strict_types=1);

namespace App\Tests\Service\CredentialHub;

use App\Controller\PayloadValidator\PayloadValidator;
use App\Service\CredentialHub\SharedPayloadService;
use App\Service\Payload\JsonPayloadDecoder;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

final class SharedPayloadServiceTest extends TestCase
{
    public function testGetProcessIdOrFailReturnsDecodedProcessIdFromJsonStringPayload(): void
    {
        $request = Request::create('/api/credential-hub/shared/registration/state', 'POST');
        $payloadValidator = $this->createMock(PayloadValidator::class);
        $payloadValidator
            ->expects(self::once())
            ->method('validatePayload')
            ->with($request, 'shared_registration_state')
            ->willReturn([
                'shared_registration_state' => '{"processId":"process-1"}',
            ]);

        $service = new SharedPayloadService($payloadValidator, $this->createMock(LoggerInterface::class), new JsonPayloadDecoder());

        self::assertSame('process-1', $service->getProcessIdOrFail($request, 'shared_registration_state'));
    }

    public function testGetProcessIdReturnsFalseWhenProcessIdIsMissing(): void
    {
        $request = Request::create('/api/credential-hub/domain/delete/state', 'POST');
        $payloadValidator = $this->createMock(PayloadValidator::class);
        $payloadValidator
            ->expects(self::once())
            ->method('validatePayload')
            ->with($request, 'domain_delete_state')
            ->willReturn([
                'domain_delete_state' => ['targetId' => 'target-1'],
            ]);

        $service = new SharedPayloadService($payloadValidator, $this->createMock(LoggerInterface::class), new JsonPayloadDecoder());

        self::assertFalse($service->getProcessId($request, 'domain_delete_state'));
    }

    public function testGetProcessIdOrFailThrowsWhenProcessIdIsMissing(): void
    {
        $request = Request::create('/api/credential-hub/domain/delete/state', 'POST');
        $payloadValidator = $this->createMock(PayloadValidator::class);
        $payloadValidator
            ->expects(self::once())
            ->method('validatePayload')
            ->with($request, 'domain_delete_state')
            ->willReturn([
                'domain_delete_state' => ['targetId' => 'target-1'],
            ]);

        $service = new SharedPayloadService($payloadValidator, $this->createMock(LoggerInterface::class), new JsonPayloadDecoder());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Payload key "domain_delete_state" must contain processId.');

        $service->getProcessIdOrFail($request, 'domain_delete_state');
    }

    public function testDecodeJsonRejectsInvalidJson(): void
    {
        $service = new SharedPayloadService($this->createMock(PayloadValidator::class), $this->createMock(LoggerInterface::class), new JsonPayloadDecoder());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Payload key "payload" must contain valid JSON.');

        $service->decodeJson(['payload' => '{invalid'], 'payload');
    }

    public function testGetPayloadOrFailReturnsValidatedArrayPayload(): void
    {
        $request = Request::create('/api/credential-hub/domain/read/credential', 'POST');
        $payloadValidator = $this->createMock(PayloadValidator::class);
        $payloadValidator
            ->expects(self::once())
            ->method('validatePayload')
            ->with($request, 'domain_read_credential')
            ->willReturn([
                'domain_read_credential' => [
                    'domainProcessId' => 'process-1',
                    'type' => 'domain-login',
                ],
            ]);

        $service = new SharedPayloadService($payloadValidator, $this->createMock(LoggerInterface::class), new JsonPayloadDecoder());

        self::assertSame([
            'domainProcessId' => 'process-1',
            'type' => 'domain-login',
        ], $service->getPayloadOrFail($request, 'domain_read_credential'));
    }

    public function testGetPayloadDecodesJsonStringPayload(): void
    {
        $request = Request::create('/api/credential-hub/vault/read/credential/decrypted', 'POST');
        $payloadValidator = $this->createMock(PayloadValidator::class);
        $payloadValidator
            ->expects(self::once())
            ->method('validatePayload')
            ->with($request, 'vault_read_credential_encrypted')
            ->willReturn([
                'vault_read_credential_encrypted' => '{"publicId":"public-1"}',
            ]);

        $service = new SharedPayloadService($payloadValidator, $this->createMock(LoggerInterface::class), new JsonPayloadDecoder());

        self::assertSame(['publicId' => 'public-1'], $service->getPayloadOrFail($request, 'vault_read_credential_encrypted'));
    }
}
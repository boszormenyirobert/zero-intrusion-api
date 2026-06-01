<?php

declare(strict_types=1);

namespace App\Tests\Service\CredentialHub\Shared;

use App\Service\CredentialHub\Shared\SharedRegistrationService;
use App\Controller\PayloadValidator\PayloadValidator;
use App\DTO\CredentialHub\Shared\SharedRegistrationNewToEncryptResultDTO;
use App\Service\CredentialHub\Shared\SharedRegistrationNewToEncryptService;
use App\Service\Payload\JsonPayloadDecoder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class SharedRegistrationNewToEncryptServiceTest extends TestCase
{
    public function testHandleLoadsUserCredentialByRegistrationProcessId(): void
    {
        $request = Request::create('/api/shared/registration/new-to-encrypt', 'POST');

        $payloadValidator = $this->createMock(PayloadValidator::class);
        $payloadValidator
            ->expects(self::once())
            ->method('validatePayload')
            ->with($request, 'shared_registration_new_to_encrypt')
            ->willReturn([
                'shared_registration_new_to_encrypt' => json_encode([
                    'registrationProcessId' => 'process-123',
                ], JSON_THROW_ON_ERROR),
            ]);

        $sharedRegistrationService = $this->createMock(SharedRegistrationService::class);
        $sharedRegistrationService
            ->expects(self::once())
            ->method('getUserCredentialFromAuthBridge')
            ->with('process-123')
            ->willReturn(['credential' => 'secret']);

        $service = new SharedRegistrationNewToEncryptService($payloadValidator, $sharedRegistrationService, new JsonPayloadDecoder());

        self::assertEquals(
            new SharedRegistrationNewToEncryptResultDTO(['credential' => 'secret'], ''),
            $service->handle($request)
        );
    }

    public function testHandleRejectsInvalidJsonPayload(): void
    {
        $request = Request::create('/api/shared/registration/new-to-encrypt', 'POST');

        $payloadValidator = $this->createMock(PayloadValidator::class);
        $payloadValidator
            ->expects(self::once())
            ->method('validatePayload')
            ->willReturn([
                'shared_registration_new_to_encrypt' => '{invalid-json',
            ]);

        $sharedRegistrationService = $this->createMock(SharedRegistrationService::class);
        $sharedRegistrationService
            ->expects(self::never())
            ->method('getUserCredentialFromAuthBridge');

        $service = new SharedRegistrationNewToEncryptService($payloadValidator, $sharedRegistrationService, new JsonPayloadDecoder());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid shared registration new-to-encrypt payload.');

        $service->handle($request);
    }

    public function testHandleAcceptsAlreadyDecodedPayloadArray(): void
    {
        $request = Request::create('/api/shared/registration/new-to-encrypt', 'POST');

        $payloadValidator = $this->createMock(PayloadValidator::class);
        $payloadValidator
            ->expects(self::once())
            ->method('validatePayload')
            ->with($request, 'shared_registration_new_to_encrypt')
            ->willReturn([
                'shared_registration_new_to_encrypt' => [
                    'registrationProcessId' => 'process-123',
                ],
            ]);

        $sharedRegistrationService = $this->createMock(SharedRegistrationService::class);
        $sharedRegistrationService
            ->expects(self::once())
            ->method('getUserCredentialFromAuthBridge')
            ->with('process-123')
            ->willReturn(['credential' => 'secret']);

        $service = new SharedRegistrationNewToEncryptService($payloadValidator, $sharedRegistrationService, new JsonPayloadDecoder());

        self::assertEquals(
            new SharedRegistrationNewToEncryptResultDTO(['credential' => 'secret'], ''),
            $service->handle($request)
        );
    }
}

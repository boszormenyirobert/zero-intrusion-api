<?php

declare(strict_types=1);

namespace App\Tests\Service\CredentialHub\Shared;

use App\Controller\PayloadValidator\PayloadValidator;
use App\DTO\CredentialHub\Shared\SharedRegistrationNewResultDTO;
use App\Service\AccessRegistry\AccessRegistryRegistrationService;
use App\Service\CredentialHub\Shared\SharedRegistrationNewService;
use App\Service\Payload\JsonPayloadDecoder;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class SharedRegistrationNewServiceTest extends TestCase
{
    public function testHandleRegistersDomainFlowWithoutNotification(): void
    {
        $request = Request::create('/api/shared/registration/new', 'POST');
        $user = [
            'type' => 'registration-domain',
            'registrationProcessId' => 'process-123',
        ];

        $payloadValidator = $this->createMock(PayloadValidator::class);
        $payloadValidator
            ->expects(self::once())
            ->method('validatePayload')
            ->with($request, 'shared_registration_new')
            ->willReturn([
                'shared_registration_new' => json_encode($user, JSON_THROW_ON_ERROR),
            ]);

        $registrationService = $this->createMock(AccessRegistryRegistrationService::class);
        $registrationService
            ->expects(self::once())
            ->method('addAccessRegistry')
            ->with($user, 'domain', false)
            ->willReturn(['id' => 10]);
        $registrationService
            ->expects(self::never())
            ->method('sendNotification');

        $service = new SharedRegistrationNewService($payloadValidator, $registrationService, new JsonPayloadDecoder());

        self::assertEquals(
            new SharedRegistrationNewResultDTO(['id' => 10], ''),
            $service->handle($request)
        );
    }

    public function testHandleTriggersNotificationForSystemHubRegistration(): void
    {
        $request = Request::create('/api/shared/registration/new', 'POST');
        $user = [
            'type' => 'system_hub_registration',
            'registrationProcessId' => 'process-123',
        ];
        $registeredUser = ['id' => 10];

        $payloadValidator = $this->createMock(PayloadValidator::class);
        $payloadValidator
            ->expects(self::once())
            ->method('validatePayload')
            ->willReturn([
                'shared_registration_new' => json_encode($user, JSON_THROW_ON_ERROR),
            ]);

        $registrationService = $this->createMock(AccessRegistryRegistrationService::class);
        $registrationService
            ->expects(self::once())
            ->method('addAccessRegistry')
            ->with($user, 'domain', true)
            ->willReturn($registeredUser);
        $registrationService
            ->expects(self::once())
            ->method('sendNotification')
            ->with($registeredUser, $user);

        $service = new SharedRegistrationNewService($payloadValidator, $registrationService, new JsonPayloadDecoder());

        self::assertEquals(new SharedRegistrationNewResultDTO($registeredUser, ''), $service->handle($request));
    }

    public function testHandleRejectsInvalidJsonPayload(): void
    {
        $request = Request::create('/api/shared/registration/new', 'POST');

        $payloadValidator = $this->createMock(PayloadValidator::class);
        $payloadValidator
            ->expects(self::once())
            ->method('validatePayload')
            ->willReturn([
                'shared_registration_new' => '{invalid-json',
            ]);

        $registrationService = $this->createMock(AccessRegistryRegistrationService::class);
        $registrationService
            ->expects(self::never())
            ->method('addAccessRegistry');

        $service = new SharedRegistrationNewService($payloadValidator, $registrationService, new JsonPayloadDecoder());

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid shared registration new payload.');

        $service->handle($request);
    }

    public function testHandleAcceptsAlreadyDecodedPayloadArray(): void
    {
        $request = Request::create('/api/shared/registration/new', 'POST');
        $user = [
            'type' => 'application-registration',
            'registrationProcessId' => 'process-123',
        ];

        $payloadValidator = $this->createMock(PayloadValidator::class);
        $payloadValidator
            ->expects(self::once())
            ->method('validatePayload')
            ->with($request, 'shared_registration_new')
            ->willReturn([
                'shared_registration_new' => $user,
            ]);

        $registrationService = $this->createMock(AccessRegistryRegistrationService::class);
        $registrationService
            ->expects(self::once())
            ->method('addAccessRegistry')
            ->with($user, 'application', false)
            ->willReturn(['id' => 11]);

        $service = new SharedRegistrationNewService($payloadValidator, $registrationService, new JsonPayloadDecoder());

        self::assertEquals(new SharedRegistrationNewResultDTO(['id' => 11], ''), $service->handle($request));
    }
}

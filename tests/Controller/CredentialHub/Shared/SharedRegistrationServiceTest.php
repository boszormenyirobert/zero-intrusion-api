<?php

declare(strict_types=1);

namespace App\Tests\Controller\CredentialHub\Shared;

use App\Service\CredentialHub\Shared\SharedRegistrationService;
use App\Service\AuthBridge\AuthBridgeService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class SharedRegistrationServiceTest extends TestCase
{
    public function testGetQrContentBuildsSharedRegistrationDto(): void
    {
        $payload = (object) [
            'type' => 'registration-domain',
            'source' => 'extension',
            'isNew' => 'true',
            'userPublicId' => 'public-1',
            'targetId' => 'target-1',
        ];

        $service = new SharedRegistrationService($this->createMock(LoggerInterface::class), $this->createMock(AuthBridgeService::class));
        $dto = $service->getQrContent($payload, 'auth-1', 'process-1');

        self::assertSame('process-1', $dto->registrationProcessId);
        self::assertSame('auth-1', $dto->xExtensionAuthOne);
        self::assertSame('registration-domain', $dto->type);
        self::assertSame('extension', $dto->source);
        self::assertSame('true', $dto->isNew);
        self::assertSame('public-1', $dto->userPublicId);
        self::assertSame('target-1', $dto->targetId);
    }

    public function testGetExtendedQrContentSetsDomainOrApplicationDependingOnType(): void
    {
        $service = new SharedRegistrationService($this->createMock(LoggerInterface::class), $this->createMock(AuthBridgeService::class));

        $domainPayload = (object) ['domain' => 'example.test'];
        $domainQr = $service->getQrContent((object) [
            'type' => 'registration-domain',
            'source' => 'extension',
            'isNew' => 'true',
            'userPublicId' => null,
            'targetId' => null,
        ], 'auth-1', 'process-1');

        $applicationPayload = (object) ['application' => 'vault-app'];
        $applicationQr = $service->getQrContent((object) [
            'type' => 'registration-application',
            'source' => 'extension',
            'isNew' => 'false',
            'userPublicId' => null,
            'targetId' => null,
        ], 'auth-2', 'process-2');

        self::assertSame('example.test', $service->getExtendedQrContent('registration-domain', $domainQr, $domainPayload)->domain);
        self::assertSame('vault-app', $service->getExtendedQrContent('registration-application', $applicationQr, $applicationPayload)->application);
    }

    public function testOneTouchAndAuthBridgeDelegationsWork(): void
    {
        $authBridgeService = $this->createMock(AuthBridgeService::class);
        $authBridgeService
            ->expects(self::once())
            ->method('saveUserCredentialInAuthBridge')
            ->with([
                'userName' => 'user@example.test',
                'userPassword' => 'secret',
                'description' => 'demo',
            ], 'registration-1')
            ->willReturn(true);
        $authBridgeService
            ->expects(self::once())
            ->method('getUserCredentialFromAuthBridge')
            ->with('registration-1')
            ->willReturn('credential-json');

        $service = new SharedRegistrationService($this->createMock(LoggerInterface::class), $authBridgeService);
        $oneTouch = $service->getOneTouchQrContent((object) [
            'type' => 'vault-edit',
            'source' => 'extension',
        ], 'auth-1', 'process-1');

        self::assertSame('process-1', $oneTouch->getOneTouchProcessId());
        self::assertSame('auth-1', $oneTouch->getXExtensionAuthOne());

        $service->saveUserCredentialInAuthBridge((object) [
            'userName' => 'user@example.test',
            'userPassword' => 'secret',
            'description' => 'demo',
        ], 'registration-1');

        self::assertSame('credential-json', $service->getUserCredentialFromAuthBridge('registration-1'));
    }
}
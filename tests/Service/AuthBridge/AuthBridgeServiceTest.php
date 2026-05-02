<?php

declare(strict_types=1);

namespace App\Tests\Service\AuthBridge;

use App\DTO\QR\CredentialHubIdentityDTO;
use App\Entity\AuthBridge;
use App\Service\AuthBridge\AuthBridgeHandler\Application\Fetch;
use App\Service\AuthBridge\AuthBridgeHandler\AuthBridgeHandler;
use App\Service\AuthBridge\AuthBridgeHandler\Domain\Credential;
use App\Service\AuthBridge\AuthBridgeHandler\Identity;
use App\Service\AuthBridge\AuthBridgeService;
use PHPUnit\Framework\TestCase;

final class AuthBridgeServiceTest extends TestCase
{
    public function testPersistDecryptedUserDataDelegatesToHandler(): void
    {
        $user = ['type' => 'domain-login', 'publicId' => 'public-1'];

        $handler = $this->createMock(AuthBridgeHandler::class);
        $handler
            ->expects(self::once())
            ->method('persistDecryptedUserData')
            ->with($user)
            ->willReturn(true);

        $service = $this->createService(handler: $handler);

        self::assertTrue($service->persistDecryptedUserData($user));
    }

    public function testGenerateRequestIdentityDelegatesToIdentityHandler(): void
    {
        $dto = new CredentialHubIdentityDTO();
        $dto->setRegistrationProcessId('registration-123');

        $identity = $this->createMock(Identity::class);
        $identity
            ->expects(self::once())
            ->method('generateRequestIdentity')
            ->with('registrationProcessId')
            ->willReturn($dto);

        $service = $this->createService(identity: $identity);

        self::assertSame($dto, $service->generateRequestIdentity('registrationProcessId'));
    }

    public function testFetchForOneTouchDelegatesToFetchHandler(): void
    {
        $authBridge = (new AuthBridge())->setOneTouchProcessId('one-touch-123');

        $fetch = $this->createMock(Fetch::class);
        $fetch
            ->expects(self::once())
            ->method('fetchForOneTouch')
            ->with('one-touch-123', 'oneTouchProcessId')
            ->willReturn($authBridge);

        $service = $this->createService(fetch: $fetch);

        self::assertSame($authBridge, $service->fetchForOneTouch('one-touch-123', 'oneTouchProcessId'));
    }

    public function testUpdateProcessStateDelegatesToHandler(): void
    {
        $handler = $this->createMock(AuthBridgeHandler::class);
        $handler
            ->expects(self::once())
            ->method('updateProcessState')
            ->with('registrationProcessId', 'process-123');

        $service = $this->createService(handler: $handler);
        $service->updateProcessState('registrationProcessId', 'process-123');

        self::assertTrue(true);
    }

    private function createService(
        ?Identity $identity = null,
        ?Credential $credential = null,
        ?AuthBridgeHandler $handler = null,
        ?Fetch $fetch = null,
    ): AuthBridgeService {
        return new AuthBridgeService(
            $identity ?? $this->createMock(Identity::class),
            $credential ?? $this->createMock(Credential::class),
            $handler ?? $this->createMock(AuthBridgeHandler::class),
            $fetch ?? $this->createMock(Fetch::class),
        );
    }
}

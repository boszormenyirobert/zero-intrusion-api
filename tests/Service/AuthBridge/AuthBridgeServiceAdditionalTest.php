<?php

declare(strict_types=1);

namespace App\Tests\Service\AuthBridge;

use App\Entity\AuthBridge;
use App\Service\AuthBridge\AuthBridgeHandler\Application\Fetch;
use App\Service\AuthBridge\AuthBridgeHandler\AuthBridgeHandler;
use App\Service\AuthBridge\AuthBridgeHandler\Domain\Credential;
use App\Service\AuthBridge\AuthBridgeHandler\Identity;
use App\Service\AuthBridge\AuthBridgeService;
use PHPUnit\Framework\TestCase;

final class AuthBridgeServiceAdditionalTest extends TestCase
{
    public function testRemainingHandlerDelegationsReturnUnderlyingValues(): void
    {
        $user = ['publicId' => 'public-1'];
        $credentialPayload = ['credential' => 'secret'];

        $handler = $this->createMock(AuthBridgeHandler::class);
        $handler->expects(self::once())->method('getDecryptedUserData')->with($user)->willReturn(true);
        $handler->expects(self::once())->method('getDecryptedUserDataToMobileRequest')->with($user)->willReturn($credentialPayload);
        $handler->expects(self::once())->method('persistDecryptedUserDataForWeb')->with($user)->willReturn(['web' => true]);
        $handler->expects(self::once())->method('persistOneTouchUserData')->with($user)->willReturn(true);
        $handler->expects(self::once())->method('saveUserCredentialInAuthBridge')->with(['username' => 'user'], 'process-1')->willReturn(true);
        $handler->expects(self::once())->method('getUserCredentialFromAuthBridge')->with('process-1')->willReturn('credential-json');

        $service = $this->createService(handler: $handler);

        self::assertTrue($service->getDecryptedUserData($user));
        self::assertSame($credentialPayload, $service->getDecryptedUserDataToMobileRequest($user));
        self::assertSame(['web' => true], $service->persistDecryptedUserDataForWeb($user));
        self::assertTrue($service->persistOneTouchUserData($user));
        self::assertTrue($service->saveUserCredentialInAuthBridge(['username' => 'user'], 'process-1'));
        self::assertSame('credential-json', $service->getUserCredentialFromAuthBridge('process-1'));
    }

    public function testCredentialAndFetchDelegationsReturnExpectedData(): void
    {
        $credential = $this->createMock(Credential::class);
        $credential
            ->expects(self::once())
            ->method('getUserCredentialsByDomainProcessId')
            ->with('domain-process-1')
            ->willReturn(['credential' => 'secret']);

        $fetch = $this->createMock(Fetch::class);
        $fetch
            ->expects(self::once())
            ->method('fetchFromAccessTable')
            ->with('application-process-1', 'applicationProcessId')
            ->willReturn(['targetId' => 'target-1']);

        $authBridge = (new AuthBridge())->setOneTouchProcessId('one-touch-1');
        $fetch
            ->expects(self::once())
            ->method('fetchForOneTouch')
            ->with('one-touch-1', 'oneTouchProcessId')
            ->willReturn($authBridge);

        $service = $this->createService(credential: $credential, fetch: $fetch);

        self::assertSame(['credential' => 'secret'], $service->getUserCredentialsByDomainProcessId('domain-process-1'));
        self::assertSame(['targetId' => 'target-1'], $service->fetchFromAccessTable('application-process-1', 'applicationProcessId'));
        self::assertSame($authBridge, $service->fetchForOneTouch('one-touch-1', 'oneTouchProcessId'));
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
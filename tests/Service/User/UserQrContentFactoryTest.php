<?php

declare(strict_types=1);

namespace App\Tests\Service\User;

use App\DTO\QR\CorporateRegistrationDTO;
use App\DTO\QR\CredentialHubIdentityDTO;
use App\DTO\QR\UserLoginDTO;
use App\Exception\CorporateRegistrationException;
use App\Service\User\UserQrContentFactory;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

final class UserQrContentFactoryTest extends TestCase
{
    public function testCreateReturnsLoginPayloadForDomainProcess(): void
    {
        $factory = new UserQrContentFactory($this->createMock(LoggerInterface::class));
        $identity = new CredentialHubIdentityDTO();
        $identity->setXExtensionAuthOne('auth-1');
        $identity->setDomainProcessId('process-1');

        $result = $factory->create([
            'domain' => 'example.test',
            'corporatePublicId' => 'corp-1',
            'corporateAuthentication' => 'signature',
        ], $identity, 'domainProcessId');

        self::assertInstanceOf(UserLoginDTO::class, $result);
        self::assertSame('example.test', $result->domain);
        self::assertSame('process-1', $result->domainProcessId);
        self::assertSame('corp-1', $result->corporateId);
    }

    public function testCreateReturnsRegistrationPayloadAndWarnsWhenMultipleAuthenticationsExist(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects(self::once())
            ->method('warning')
            ->with('Registration payload received multiple corporate authentication values; using the first entry.');

        $factory = new UserQrContentFactory($logger);
        $identity = new CredentialHubIdentityDTO();
        $identity->setXExtensionAuthOne('auth-1');
        $identity->setRegistrationProcessId('registration-1');

        $result = $factory->create([
            'domain' => 'example.test',
            'corporatePublicId' => 'corp-1',
            'corporateAuthentication' => ['signature-1', 'signature-2'],
        ], $identity, 'registrationProcessId');

        self::assertInstanceOf(CorporateRegistrationDTO::class, $result);
        self::assertSame('signature-1', $result->getCorporateAuthentication());
        self::assertSame('registration-1', $result->getRegistrationProcessId());
    }

    public function testCreateRejectsUnsupportedProcessKey(): void
    {
        $factory = new UserQrContentFactory($this->createMock(LoggerInterface::class));

        $this->expectException(CorporateRegistrationException::class);
        $this->expectExceptionMessage('Unsupported process key: unsupportedProcessId');

        $factory->create([], new CredentialHubIdentityDTO(), 'unsupportedProcessId');
    }
}
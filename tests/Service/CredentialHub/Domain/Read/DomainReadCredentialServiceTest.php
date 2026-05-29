<?php

declare(strict_types=1);

namespace App\Tests\Service\CredentialHub\Domain\Read;

use App\Controller\CredentialHub\Domain\Read\DomainService;
use App\Service\CredentialHub\Domain\Read\DomainReadCredentialService;
use App\Service\CredentialHub\SharedPayloadService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

final class DomainReadCredentialServiceTest extends TestCase
{
    public function testHandleReturnsCredentialsFromDomainService(): void
    {
        $request = Request::create('/api/credential-hub/domain/read/credential', 'POST');
        $user = [
            'domainProcessId' => 'process-1',
            'type' => 'domain-login',
            'source' => 'extension',
        ];

        $sharedPayloadService = $this->createMock(SharedPayloadService::class);
        $sharedPayloadService
            ->expects(self::once())
            ->method('getPayload')
            ->with($request, 'domain_read_credential')
            ->willReturn($user);

        $domainService = $this->createMock(DomainService::class);
        $domainService
            ->expects(self::once())
            ->method('processCredentialRead')
            ->with($user)
            ->willReturn(true);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::exactly(2))->method('info');

        $service = new DomainReadCredentialService($sharedPayloadService, $domainService, $logger);

        self::assertSame(['credentials' => true], $service->handle($request));
    }
}
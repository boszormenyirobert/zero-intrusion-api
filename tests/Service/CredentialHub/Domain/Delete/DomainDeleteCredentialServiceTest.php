<?php

declare(strict_types=1);

namespace App\Tests\Service\CredentialHub\Domain\Delete;

use App\Controller\CredentialHub\Domain\Delete\DomainDeleteService;
use App\DTO\CredentialHub\Domain\Delete\DomainDeleteCredentialResultDTO;
use App\Service\CredentialHub\Domain\Delete\DomainDeleteCredentialService;
use App\Service\CredentialHub\SharedPayloadService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

final class DomainDeleteCredentialServiceTest extends TestCase
{
    public function testHandleReturnsDomainDeleteProcess(): void
    {
        $request = Request::create('/api/credential-hub/domain/delete/credential', 'POST');
        $process = ['removeProcessId' => 'process-1'];

        $sharedPayloadService = $this->createMock(SharedPayloadService::class);
        $sharedPayloadService
            ->expects(self::once())
            ->method('getProcessId')
            ->with($request, 'domain_delete_credential', true)
            ->willReturn($process);

        $domainDeleteService = $this->createMock(DomainDeleteService::class);
        $domainDeleteService
            ->expects(self::once())
            ->method('deleteDomain')
            ->with($process)
            ->willReturn(true);

        $service = new DomainDeleteCredentialService($sharedPayloadService, $domainDeleteService);

        self::assertEquals(
            new DomainDeleteCredentialResultDTO(true, ''),
            $service->handle($request)
        );
    }
}
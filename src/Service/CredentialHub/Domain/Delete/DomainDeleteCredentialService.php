<?php

declare(strict_types=1);

namespace App\Service\CredentialHub\Domain\Delete;

use App\Controller\CredentialHub\PayloadKeys;
use App\Controller\CredentialHub\Domain\Delete\DomainDeleteService;
use App\DTO\CredentialHub\Domain\Delete\DomainDeleteCredentialResultDTO;
use App\Service\CredentialHub\SharedPayloadService;
use Symfony\Component\HttpFoundation\Request;

class DomainDeleteCredentialService
{
    public function __construct(
        private readonly SharedPayloadService $sharedPayloadService,
        private readonly DomainDeleteService $domainDeleteService,
    ) {
    }

    public function handle(Request $request): ?DomainDeleteCredentialResultDTO
    {
        $process = $this->sharedPayloadService->getProcessId($request, PayloadKeys::DOMAIN_DELETE_CREDENTIAL, true);

        if (!$process) {
            return null;
        }

        return new DomainDeleteCredentialResultDTO(
            $this->domainDeleteService->deleteDomain($process),
            ''
        );
    }
}
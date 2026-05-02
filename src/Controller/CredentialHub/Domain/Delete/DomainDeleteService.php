<?php

namespace App\Controller\CredentialHub\Domain\Delete;

use App\DTO\QR\DomainDeleteQrContentDTO;
use App\Service\AccessRegistry\CredentialHubHandler\DeleteDomain;

class DomainDeleteService
{
    public function __construct(
        private DeleteDomain $deleteDomain,
    ) {}

    public function getQrContent(
        ?string $domain,
        ?string $type,
        ?string $source,
        ?string $targetId,
        string $mobilXExtensionAuth,
        string $processId
    ): DomainDeleteQrContentDTO
    {
        return new DomainDeleteQrContentDTO(
            $mobilXExtensionAuth,
            $domain,
            $type,
            $source,
            $targetId,
            $processId
        );
    }

    public function deleteDomain($process)
    {
        return $this->deleteDomain->handleDomainDeletion($process);
    }
}
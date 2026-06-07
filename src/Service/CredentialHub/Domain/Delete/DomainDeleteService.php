<?php

namespace App\Service\CredentialHub\Domain\Delete;

use App\DTO\QR\DomainDeleteQrContentDTO;
use App\Service\AccessRegistry\CredentialHubHandler\DeleteDomain;

class DomainDeleteService
{
    public function __construct(
        private DeleteDomain $deleteDomain,
    ) {}

    public function getQrContent(
        string $mobilXExtensionAuth,        
        ?string $type,
        ?string $source,
        ?string $targetId,
        ?string $processId,
        ?string $domain
    ): DomainDeleteQrContentDTO
    {
        return new DomainDeleteQrContentDTO(
            $mobilXExtensionAuth,            
            $type,
            $source,
            $targetId,
            $processId,
            $domain
        );
    }

    public function deleteDomain($process)
    {
        return $this->deleteDomain->handleDomainDeletion($process);
    }
}
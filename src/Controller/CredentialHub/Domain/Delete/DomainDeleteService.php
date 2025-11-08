<?php

namespace App\Controller\CredentialHub\Domain\Delete;

use App\DTO\QR\DomainDeleteQrContentDTO;
use App\Service\AccessRegistry\CredentialHubHandler\DeleteDomain;
use App\Controller\CredentialHub\SharedService;
use App\Controller\PayloadValidator\PayloadValidator;

class DomainDeleteService
{
    public function __construct(
        private DeleteDomain $deleteDomain,
        private SharedService $sharedService,
        private PayloadValidator $payloadValidator,
    ) {}    

    /**
     * Creates the content to be encoded in the QR code.
     *
     * @param array $validatedPayload The decoded payload array.
     * @param string $mobilXExtensionAuth The mobile extension HMAC.
     * @param string $processId The process identifier.
     * @return array The structured QR content.
     */
    public function getQrContent(array $validatedPayload, string $mobilXExtensionAuth, string $processId): DomainDeleteQrContentDTO
    {
        return new DomainDeleteQrContentDTO(
            $mobilXExtensionAuth,
            $validatedPayload['domain'] ?? null,
            $validatedPayload['type'] ?? null,
            $validatedPayload['source'] ?? null,
            $validatedPayload['targetId'] ?? null,
            $processId
        );
    }

    public function deleteDomain($process){
            return $this->deleteDomain->handleDomainDeletion($process);
    }
}
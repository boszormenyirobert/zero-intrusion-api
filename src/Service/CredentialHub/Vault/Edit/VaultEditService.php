<?php

namespace App\Service\CredentialHub\Vault\Edit;

use App\DTO\QR\VaultEditQrContentDTO;

class VaultEditService
{
    public function getQrContent($validatedPayload, $mobilXExtensionAuth, $processId): VaultEditQrContentDTO
    {
        return new VaultEditQrContentDTO(
            $validatedPayload->source,
            $validatedPayload->targetId,
            $validatedPayload->type,
            $mobilXExtensionAuth,
            $processId,
            $validatedPayload->application
        );
    }
}
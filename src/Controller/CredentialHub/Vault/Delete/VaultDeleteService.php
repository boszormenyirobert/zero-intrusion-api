<?php

namespace App\Controller\CredentialHub\Vault\Delete;

use App\Service\AccessRegistry\CredentialHubHandler\DeleteApplication;
use App\Service\AuthBridge\AuthBridgeService;
use App\Service\QrService\QrService;
use App\Controller\CredentialHub\SharedService;


class VaultDeleteService
{
        public function __construct(
            private DeleteApplication $deleteApplicationHandler,
            private AuthBridgeService $authBridgeService,
            private QrService $qrService,
            private SharedService $sharedService
    ) {}

    public function deleteApplication($process):array
    {        
        $deleteApplicationDto = $this->sharedService->getApplicationDto($process);

        return $this->deleteApplicationHandler->deleteApplication($deleteApplicationDto);
    }    
}
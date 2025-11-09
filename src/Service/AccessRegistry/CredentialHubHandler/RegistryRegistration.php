<?php

namespace App\Service\AccessRegistry\CredentialHubHandler;

use Psr\Log\LoggerInterface;
use App\Service\AccessRegistry\AccessRegistryDomainService;


final class RegistryRegistration
{
    public function __construct(
        private LoggerInterface $logger,
        private AccessRegistryDomainService $accessRegistryDomainService
    ) {}

    public function addAccessRegistry(array $userData, $type, $zeroIntrusionRegistration)
    {
        $result = $this->accessRegistryDomainService->isAllowedUserDomainApplicationCombination($userData, $type);
        $update = $userData['update'];


        if (!empty($result) && $result['newCombination'] === false && $update === "new" && $zeroIntrusionRegistration == false) {
            return false;
        }

        return $this->addOrUpdateRegistry($result, $userData, $update, $type);
    }

    private function addOrUpdateRegistry($result, $userData, $update, $type)
    {
        if($update === "new"){
            $targetId = $this->getSubString(50);
        } 
        $this->logger->critical("Generated Target ID: " . json_encode($userData));

        $targetId = $userData['targetId'];;

        if ($result['newCombination'] === false && $update) {
            $this->accessRegistryDomainService->deleteDomainRegistraions($userData);
        }
        if($update === "new"){
                $userData['targetId'] = $targetId;
        }
        return $this->accessRegistryDomainService->createDomain($userData, $type);
    }

    private function getSubString($length){
        return substr(str_shuffle(str_repeat('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', $length)), 0, $length);
    }
}
<?php

namespace App\Service\AccessRegistry;

use Psr\Log\LoggerInterface;
use App\Service\AuthBridge\AuthBridgeService;
use App\Service\AccessRegistry\CredentialHubResolver\ResolverService;
use App\Service\Cache\ProcessStateCacheService;

final class AccessRegistryDomainService
{

    public function __construct(
        private LoggerInterface $logger,
        private AuthBridgeService $authBridgeService,
        private ResolverService $resolverService,
        private ProcessStateCacheService $processStateCacheService
    ) {}

    public function isAllowedUserDomainApplicationCombination($user, $type): array
    {
        $encryptedUserPages = $this->resolverService->getFilter()->getUserRegistratedPages($user, $type);
        $decryptedUserPages = [];

        $result = [
            "newCombination" => true,
            "existingPage" => ""
        ];
        if (!empty($encryptedUserPages)) {
            $decryptedUserPages = $this->resolverService->getDecrypt()->getUserDecryptedPages($encryptedUserPages, $type);
        }

        if (!empty($decryptedUserPages)) {
            $result = $this->resolverService->getCheck()->userDomainCombinationExists($user, $decryptedUserPages, $type);
        }

        return $result;
    }

    public function createDomain($userData, $type)
    {
        $processKey = 'registrationProcessId';
        $processId = $userData['registrationProcessId'];
        
        //$this->authBridgeService->updateProcessState($processKey, $processId);
        $encryptedUserData = $this->resolverService->getWrite()->createAccessRegistryDomain($userData, $type);

        $userData['encryptedAuthId'] = $encryptedUserData->getUserCredential();

        $this->writeLoginEntryInRedis($processId, [
            'process' => true,
            'validation' => true,
            'process_check' => true,
            'success' => true,
        ]);
        
        return $userData;
    }    

    public function deleteDomainRegistraions($user, $type = 'domain')
    {
        $encryptedUserPages =  $this->resolverService->getFilter()->getUserRegistratedPages($user, $type);
        $collection = [];

        if (!empty($encryptedUserPages)) {
            $collection = $this->resolverService->getDecrypt()->getUserEncryptedDecryptedPageCollection($encryptedUserPages);
        }

        if (!empty($collection)) {
            $this->resolverService->getDelete()->deleteUserDomainCombination($user, $collection);
        }
    }

    /** use case not found */
    public function getDecryptedUser($user, $type)
    {
        $decryptedUserPages = [];
        $decryptedPage = null;

        $encryptedUserPages = $this->resolverService->getFilter()->getUserRegistratedPages($user, $type);

        if (!empty($encryptedUserPages)) {
            $decryptedUserPages =  $this->resolverService->getDecrypt()->getUserDecryptedPages($encryptedUserPages, $type);
        }

        if (!empty($encryptedUserPages) && !empty($decryptedUserPages)) {
            $decryptedPage = $this->resolverService->getCheck()->getUserDomainCombination($user, $decryptedUserPages);
        }

        return $decryptedPage;
    }

    private function writeLoginEntryInRedis(string $processId, array $status) {       
        $this->processStateCacheService->set(
            $processId,
            json_encode($status, JSON_UNESCAPED_UNICODE),
            300
        );

        $this->logger->info(sprintf('Process state cached for processId: %s', $processId));
    }    
}

<?php

declare(strict_types=1);

namespace App\Service\AuthBridge\AuthBridgeHandler\Domain;

use App\DTO\CredentialHub\ResponseDTO;
use App\Entity\AuthBridge;
use App\Repository\AuthBridgeRepository;
use App\Service\AccessRegistry\Database\LoginDatabaseService;
use App\Service\Crypters\CrypterDatabaseLoginService;
use JsonException;
use Psr\Log\LoggerInterface;

class Credential
{
    public function __construct(
        private readonly AuthBridgeRepository $authBridgeRepository,
        private readonly LoggerInterface $logger,
        private readonly CrypterDatabaseLoginService $crypterDatabaseLoginService,
        private readonly LoginDatabaseService $loginDatabaseService
    ) {}

    /**
     * deprecated
     * 
     * Retrieves user credentials by domainProcessId.
     *
     * @param string $domainProcessId
     * @return ResponseDTO[]
     */
    public function getUserCredentialsByDomainProcessId(string $domainProcessId): array
    {
        $authBridges = $this->findValidUser($domainProcessId);
        $authBridgeResponses = [];
        
        foreach ($authBridges as $authBridgeResponse) {
            $user = $authBridgeResponse->getData();
           
            if ($authBridgeResponse->isProcessCheck()) {
                $decryptedLogin = $this->crypterDatabaseLoginService->decryptFromDatabase($user, 'applications');

                if ($decryptedLogin && $decryptedLogin->getApplications()) {
                    $credentialsArray = $this->decodeCredentials((string) $decryptedLogin->getApplications());
                    $mappedUserDataCollection = $this->mapUserData($credentialsArray, $authBridgeResponse);
                    
                    foreach ($mappedUserDataCollection as $mappedUserData) {
                        $authBridgeResponses[] = $mappedUserData;
                    }
                }
            }
        }

        return $authBridgeResponses;
    }

    /**
    * @return ResponseDTO[]
    */
    private function findValidUser(string $domainProcessId): array
    {
        $authBridges = $this->authBridgeRepository->findBy(
            ['domainProcessId' => $domainProcessId],
            ['createdAt' => 'DESC']
        );
       
        $userCredentialsByDomain = [];
        foreach ($authBridges as $authBridge) {
            if (!$authBridge instanceof AuthBridge) {
                continue;
            }

            $responseDTO = new ResponseDTO(
                true,
                !$authBridge->isProcessState() ? 'Missing handy validation' : true,
                $authBridge->isProcessState() ? true : false,
                $authBridge
            );
            $userCredentialsByDomain[] = $responseDTO;
        }

        return $userCredentialsByDomain;
    }

    /**
     * @param array<int, array<string, mixed>> $decryptedCredentials
     * @return ResponseDTO[]
     */
    private function mapUserData(array $decryptedCredentials, ResponseDTO $authBridgeResponse): array
    {
        $mappedResponses = [];
        
        foreach ($decryptedCredentials as $credential) {
            $clonedResponse = clone $authBridgeResponse;
            
            $credentialData = $this->decodeCredentialData((string) ($credential['decrypted'] ?? '[]'));
            
            $clonedResponse->setCredential(json_encode($credentialData, JSON_THROW_ON_ERROR));
            $clonedResponse->setDescription($credential['description'] ?? null);
            $clonedResponse->setUserPublicId($authBridgeResponse->getData()->getPublicId());
            
            $mappedResponses[] = $clonedResponse;
        }
        
        return $mappedResponses;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function decodeCredentials(string $credentialsJson): array
    {
        try {
            $decoded = json_decode($credentialsJson, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new \RuntimeException('Invalid applications payload JSON.', 0, $exception);
        }

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeCredentialData(string $credentialJson): array
    {
        try {
            $decoded = json_decode($credentialJson, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new \RuntimeException('Invalid credential payload JSON.', 0, $exception);
        }

        return is_array($decoded) ? $decoded : [];
    }
}

<?php

namespace App\Service\AuthBridge\AuthBridgeHandler;

use App\Service\Crypters\CrypterDatabaseLoginService;
use App\Service\AccessRegistry\Database\LoginDatabaseService;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use App\DTO\QR\CredentialHubIdentityDTO;
use Psr\Log\LoggerInterface;

class Identity
{
    public function __construct(
        private CrypterDatabaseLoginService $crypterDatabaseLoginService,
        private LoginDatabaseService $loginDatabaseService,
        private ContainerBagInterface $params,  
        private LoggerInterface $logger      
    ) {}

    /**
     * Generates a secure identity object used by browser extensions and mobile apps.
     * Steps:
     * 1. Retrieves a newly generated identity structure from getBrowserExtensionIdentity().
     * 2. Reads the database timestamp (createdAt) from the identity.
     * 3. Uses predefined shared secrets and messages to generate two HMAC signatures:
     *      - XExtensionAuthOne (SHA-256) for mobile app validation.
     *      - XExtensionAuthTwo (SHA-1) for browser extension validation,
     *        chosen because the QR-data payload size is limited and SHA-1 is shorter.     
     * 4. Both HMACs protect against tampering and replay attacks.
     * 5. Returns the identity enriched with the additional security fields.
     */    
    public function generateRequestIdentity(string $processType): CredentialHubIdentityDTO
    {
        $identity = $this->getBrowserExtensionIdentity($processType);
        /* Database timestamp */
        $createdAt = $identity->getCreatedAt(); 

        // exchanged secrets for HMAC generation
        $secret =  $this->params->get('EXTENSION_REGISTRATION_POOL_SECRET');
        $message =  $this->params->get('EXTENSION_REGISTRATION_POOL_MESSAGE');

        //Used by Mobile App to verify the identity: Secure against replay attacks, and tampering
        $identity->setXExtensionAuthOne(hash_hmac('sha256', $message . '|' . $createdAt, $secret));
        
        //Used by Extension to verify the identity: Secure against replay attacks, and tampering
        $identity->setXExtensionAuthTwo(hash_hmac('sha1', $message . '|' . $createdAt, $secret));

        return $identity;        
    }

    /**
     * Generates and returns an identity payload for browser-extension workflows.
     * Steps:
     * 1. Creates two unique IDs: one for the process and one as the target identifier.
     * 2. Builds the communication structure containing a secret and the specific process ID
     *    (e.g. registrationProcessId, removeProcessId, domainProcessId).
     * 3. Creates and persists a new AuthBridge entity using the generated data.
     * 4. Maps the stored AuthBridge information into a CredentialHubIdentityDTO,
     *    dynamically assigning the process ID based on the given processType.
     * 5. Returns the fully prepared identity object for use by the browser extension.
     */    
    public function getBrowserExtensionIdentity(string $processType): CredentialHubIdentityDTO
    {
        // Generate unique IDs for process and target
        $processId = $this->getGeneratedId();
        $targetId  = $this->getGeneratedId();

        // Prepare communication data
        $validCommunication = [
            'secret' => base64_encode(random_bytes(35)),
            $processType => $processId
        ];

        // Create and persist AuthBridge
        $authBridge = $this->initializeAuthBridge($validCommunication, $processType, $targetId, $processId);
        $createdAuthBridge = $this->loginDatabaseService->addUserLogin($authBridge);

        return $this->mapAuthBridgeToDTO($authBridge, $processType, $processId);
    }

    /**
     * Maps an AuthBridge entity into a CredentialHubIdentityDTO for use by browser extensions or mobile apps.
     * Returns the fully populated CredentialHubIdentityDTO.
     */    
    private function mapAuthBridgeToDTO(\App\Entity\AuthBridge $authBridge, string $processType, string $processId): CredentialHubIdentityDTO
    {
        $identity = new CredentialHubIdentityDTO();
        $identity->setSecret($authBridge->getSecret())
                ->setCreatedAt($authBridge->getCreatedAt()->getTimestamp())
                ->setIv($authBridge->getIv());

        $setter = 'set' . ucfirst($processType);
        $identity->$setter($processId);

        return $identity;
    }

    /**
     * Prepares a new AuthBridge entity for storage in the database.
     * Steps:
     * 1. Encrypts the extension communication data using crypterDatabaseLoginService.
     * 2. Sets the target identifier and initializes the process state as false.
     * 3. Assigns the process ID to the appropriate property based on the processType:
     *      - removeProcessId → setRemoveProcessId()
     *      - registrationProcessId → setRegistrationProcessId()
     * 4. Returns the prepared AuthBridge entity ready for persistence.
     */    
    private function initializeAuthBridge($extensionValidCommunication, $processType, $targetId, $processId): \App\Entity\AuthBridge
    {
        $authBridge = $this->crypterDatabaseLoginService->encyptExtensionIdentityDataObject($extensionValidCommunication, $processType);
        $authBridge->setTargetId($targetId);      
        $authBridge->setProcessState(false);

        if($processType === 'removeProcessId'){
            $authBridge->setRemoveProcessId($processId);
        } else if($processType === 'registrationProcessId'){
            $authBridge->setRegistrationProcessId($processId);
        }

        return $authBridge;
    }

    /**
     * Generates a random alphanumeric string of fixed length (12 characters).
     * Used as unique identifiers for process IDs or target IDs.
     */    
    private function getGeneratedId(){
        $length = 12;
        return substr(str_shuffle(str_repeat('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', $length)), 0, $length);
    }
}
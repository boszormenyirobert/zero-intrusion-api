<?php

declare(strict_types=1);

namespace App\Service\AuthBridge\AuthBridgeHandler;

use App\DTO\CredentialHub\ExtensionCredentialResponseDTO;
use App\Entity\AuthBridge;
use App\Service\AccessRegistry\Database\LoginDatabaseService;
use App\Service\Crypters\CrypterDatabaseLoginService;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Psr\Log\LoggerInterface;

class Identity
{
    public function __construct(
        private readonly CrypterDatabaseLoginService $crypterDatabaseLoginService,
        private readonly LoginDatabaseService $loginDatabaseService,
        private readonly ContainerBagInterface $params,
        private readonly LoggerInterface $logger
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
    public function generateRequestIdentity(string $type): ExtensionCredentialResponseDTO
    {
        if($type === 'domain-read'){
            $processType = 'domainProcessId';
        } else if($type === 'vault-read' || $type === 'one-touch') {
            $processType = 'sessionId';
        } else if($type === 'removeProcessId') {
            $processType = 'removeProcessId';
        } else if($type === 'registrationProcessId') {
            $processType = 'registrationProcessId';
        } else {
            $this->logger->error('Invalid process type provided for identity generation', ['type' => $type]);
            throw new \InvalidArgumentException('Invalid process type provided');
        }

        $identity = $this->getBrowserExtensionIdentity($processType);
        $createdAt = $identity->getCreatedAt();

        $secret = (string) $this->params->get('EXTENSION_REGISTRATION_POOL_SECRET');
        $message = (string) $this->params->get('EXTENSION_REGISTRATION_POOL_MESSAGE');
        $qrKey = (string) $this->generateQrCacheKey();

        $identity->setXExtensionAuthOne(hash_hmac('sha256', $message . '|' . $createdAt, $secret));
        $identity->setXExtensionAuthTwo(hash_hmac('sha1', $message . '|' . $createdAt, $secret));
        $identity->setQrCacheKey($qrKey);

        return $identity;
    }

    private function generateQrCacheKey(): string
    {
        return bin2hex(random_bytes(16));
    }

    public function getBrowserExtensionIdentity(string $processType): ExtensionCredentialResponseDTO
    {
        $processId = $this->getGeneratedId();
        $targetId = $this->getGeneratedId();

        $validCommunication = [];
        $validCommunication['secret'] = base64_encode(random_bytes(35));
        $validCommunication[$processType] = $processId;

        $authBridge = $this->initializeAuthBridge($validCommunication, $processType, $targetId, $processId);
        $createdAuthBridge = $this->loginDatabaseService->addUserLogin($authBridge);

        $identity = new ExtensionCredentialResponseDTO();
        $identity->setSecret($validCommunication['secret']);
        $identity->setCreatedAt((string) $createdAuthBridge->getCreatedAt()->getTimestamp());
        $identity->setIv($authBridge->getIv());
        $method = 'set' . ucfirst($processType);
        $identity->$method($processId);

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
    private function initializeAuthBridge(array $extensionValidCommunication, string $processType, string $targetId, string $processId): AuthBridge
    {
        $authBridge = $this->crypterDatabaseLoginService->encyptExtensionIdentityDataObject($extensionValidCommunication, $processType);
        $authBridge->setTargetId($targetId);
        $authBridge->setProcessState(false);

        if ($processType === 'removeProcessId') {
            $authBridge->setRemoveProcessId($processId);
        } elseif ($processType === 'registrationProcessId') {
            $authBridge->setRegistrationProcessId($processId);
        } elseif ($processType === 'sessionId') {
            $authBridge->setSessionId($processId);
        }

        return $authBridge;
    }

    /**
     * Generates a random alphanumeric string of fixed length (12 characters).
     * Used as unique identifiers for process IDs or target IDs.
     */    
    private function getGeneratedId_Original(): string
    {
        $length = 12;
        return substr(str_shuffle(str_repeat('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', $length)), 0, $length);
    }

    /**
     * Generate a cryptographically secure unique key with 128-bit entropy.
     * Uses 16 random bytes (128 bits), encodes to base64, converts to alphanumeric,
     * and truncates to the desired length. For full 128-bit entropy, use length = 22.
     *
     * @param int $length Desired length of the generated key (default 22 for 128-bit entropy)
     * @return string Alphanumeric key
     */
    private function getGeneratedId(int $length = 22): string
    {
        $bytes = random_bytes(16);
        $base64 = rtrim(strtr(base64_encode($bytes), '+/', 'AB'), '=');

        return substr($base64, 0, $length);
    }
}
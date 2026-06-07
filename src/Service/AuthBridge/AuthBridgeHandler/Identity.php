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

    public function generateRequestIdentity(string $type): ExtensionCredentialResponseDTO
    {
        $sessionKey = $this->handleSessionKey($type);

        $identity = $this->getBrowserExtensionIdentity($sessionKey);
        $createdAt = $identity->getCreatedAt();

        $secret = (string) $this->params->get('EXTENSION_REGISTRATION_POOL_SECRET');
        $message = (string) $this->params->get('EXTENSION_REGISTRATION_POOL_MESSAGE');
        $qrKey = (string) $this->generateQrCacheKey();

        $identity->setXExtensionAuthOne(hash_hmac('sha256', $message . '|' . $createdAt, $secret));
        $identity->setXExtensionAuthTwo(hash_hmac('sha1', $message . '|' . $createdAt, $secret));
        $identity->setQrCacheKey($qrKey);

        return $identity;
    }

    private function handleSessionKey(string $type): string
    {
        return match ($type) {
            'vault-read', 'domain-read', 'one-touch', 'domain-delete', 'new-user-credential', 'credential-delete', 'application-delete' => 'sessionId',            
            'registrationProcessId' => 'registrationProcessId',

            default => $this->throwInvalidType($type),
        };
    }

    private function throwInvalidType(string $type): never
    {
        $this->logger->error(
            'Invalid process type provided for identity generation',
            ['type' => $type]
        );

        throw new \InvalidArgumentException('Invalid sessionKey type provided');
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

    private function initializeAuthBridge(array $extensionValidCommunication, string $processType, string $targetId, string $processId): AuthBridge
    {
        $authBridge = $this->crypterDatabaseLoginService->encyptExtensionIdentityDataObject($extensionValidCommunication, $processType);
        $authBridge->setTargetId($targetId);
        $authBridge->setProcessState(false);

        if ($processType === 'sessionId') {
            $authBridge->setSessionId($processId);
        } elseif ($processType === 'registrationProcessId') {
            $authBridge->setRegistrationProcessId($processId);
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
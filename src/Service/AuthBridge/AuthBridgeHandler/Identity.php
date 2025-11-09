<?php

namespace App\Service\AuthBridge\AuthBridgeHandler;

use App\Repository\IdentityRepository;
use App\Service\Crypters\CrypterDatabaseLoginService;
use App\Service\AccessRegistry\Database\LoginDatabaseService;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use App\DTO\QR\CredentialHubIdentityDTO;
use Psr\Log\LoggerInterface;

class Identity
{
    public function __construct(
        private IdentityRepository $identityRepository,
        private CrypterDatabaseLoginService $crypterDatabaseLoginService,
        private LoginDatabaseService $loginDatabaseService,
        private ContainerBagInterface $params,  
        private LoggerInterface $logger      
    ) {}

    public function generateRequestIdentity(string $processType): CredentialHubIdentityDTO
    {
        $identity = $this->getBrowserExtensionIdentity($processType);
        $createdAt = $identity->getCreatedAt(); // Database timestamp

        $secret =  $this->params->get('EXTENSION_REGISTRATION_POOL_SECRET');
        $message =  $this->params->get('EXTENSION_REGISTRATION_POOL_MESSAGE');

        $identity->setXExtensionAuthOne(hash_hmac('sha256', $message . '|' . $createdAt, $secret));
        $identity->setXExtensionAuthTwo(hash_hmac('sha1', $message . '|' . $createdAt, $secret));

        return $identity;        
    }

    public function getBrowserExtensionIdentity(string $processType): CredentialHubIdentityDTO
    {
        // $processType : registrationProcessId || removeProcessId || domainProcessId
        $processId = $this->getGeneratedId();
        $targetId = $this->getGeneratedId();

        $validCommunication['secret'] = base64_encode(random_bytes(35));
        $validCommunication[$processType] = $processId;

        $authBridge = $this->initializeAuthBridge($validCommunication, $processType, $targetId, $processId);
        $createdAuthBridge = $this->loginDatabaseService->addUserLogin($authBridge);

        $identity = new CredentialHubIdentityDTO();
        $identity->setSecret($validCommunication['secret']);
        $identity->setCreatedAt($createdAuthBridge->getCreatedAt()->getTimestamp());
        $identity->setIv($authBridge->getIv());
        $method = 'set' . ucfirst($processType);        
        $identity->$method($processId);

        return $identity;
    }

    private function initializeAuthBridge($extensionValidCommunication, $processType, $targetId, $processId): \App\Entity\AuthBridge
    {
        $authBridge = $this->crypterDatabaseLoginService->encyptExtensionIdentityDataObject($extensionValidCommunication, $processType);
        $authBridge->setTargetId($targetId);      
        $authBridge->setProcessState(false);

        if($processType === 'removeProcessId'){
        //    $authBridge->setRemoveProcessId($processId);
        } else if($processType === 'registrationProcessId'){
            $authBridge->setRegistrationProcessId($processId);
        }

        return $authBridge;
    }

    private function getGeneratedId(){
        $length = 12;
        return substr(str_shuffle(str_repeat('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', $length)), 0, $length);
    }
}
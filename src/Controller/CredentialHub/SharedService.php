<?php

namespace App\Controller\CredentialHub;

use App\Service\AccessRegistry\DTO\DeleteApplicationDto;
use App\DTO\QR\VaultDeleteQrContentDTO;
use App\Service\AuthBridge\AuthBridgeService;
use App\Service\QrService\QrService;
use App\Controller\PayloadValidator\PayloadValidator;
use Psr\Log\LoggerInterface;
use App\Service\Firebase\FirebaseService;
use App\Repository\IdentityRepository;
use App\Repository\AccessRegistryRepository;
use App\Service\Identity\Database\CrypterDatabaseIdentityService;

class SharedService
{
    public function __construct(
            private AuthBridgeService $authBridgeService,
            private QrService $qrService,
            private PayloadValidator $payloadValidator,
            private FirebaseService $firebaseService,
            private IdentityRepository $identityRepository,
            private AccessRegistryRepository $accessRegistryRepository,
            private CrypterDatabaseIdentityService $crypterDatabaseIdentityService,
            private LoggerInterface $logger
    ) {}

    public function decodeJson($validatedPayload, string $key): array{
        if (!isset($validatedPayload[$key])) {
            throw new \InvalidArgumentException("Payload key '$key' is missing.");
        }   
        if (!is_string($validatedPayload[$key])) {
            throw new \InvalidArgumentException("Payload key '$key' must be a JSON string.");
        }   
        if (empty($validatedPayload[$key])) {
            throw new \InvalidArgumentException("Payload key '$key' cannot be empty.");
        }   

        $data = $validatedPayload[$key];
        
        return json_decode($data, true);
    }

    public function getApplicationDto($user): DeleteApplicationDto{
        return new DeleteApplicationDto(
            removeProcessId: $user['removeProcessId'],
            targetId: $user['targetId']
        );
    }

    public function generateRequestIdentity($validatedPayload, $processKey):array{
            /** @var \App\DTO\QR\CredentialHubIdentityDTO $identity */        
            $identity = $this->authBridgeService->generateRequestIdentity($processKey);
            
            $method = 'get' . ucfirst($processKey);

            $qrContent = $this->getQrContent($validatedPayload, $identity->getXExtensionAuthOne(), $identity->$method());
            $qrCode = $this->qrService->getQrCode($qrContent);  
            $identity->setQrCode($qrCode);

            if($processKey === PayloadKeys::VAULT_DELETE_PROCESS_ID){
                return [
                    'toQrRead' => $identity->toRemoveProcessArray(),
                    'toNotification' => $qrContent
                ];
            }

            return [
                'toQrRead' => $identity->toRegistrationProcessArray(),
                'toNotification' => $qrContent
            ];
    }

    public function getQrContent(array $validatedPayload, $mobilXExtensionAuth, $processId): VaultDeleteQrContentDTO
    {
        return new VaultDeleteQrContentDTO(
            $validatedPayload['source'],
            $validatedPayload['targetId'],
            $validatedPayload['type'],
            $mobilXExtensionAuth,
            $processId
        );
    }  

    public function getProcessId($request, $payloadKey, $fullPayload = false){
        $validatedPayload = $this->payloadValidator->validatePayload($request, $payloadKey);
        $payload = json_decode($validatedPayload[$payloadKey] ?? '', true);

        if($fullPayload){
            return $payload;
        }
        
        if (!is_array($payload) || empty($payload['processId'])) {
            return false;
        }

        return $payload['processId'];
    }
    
    public function sendFcmNotification($source, $userPublicId, $qrContent){
        $descriptions = [
            'domainDelete' => [
                'title' => 'From domain delete',
                'body' => 'Forwarded the QR content, ordered by the user publicId',
            ],
            'domainRead' => [
                'title' => 'From domain read',
                'body' => 'Forwarded the QR content, ordered by the user publicId',
            ],
            'sharedRegistration' => [
                'title' => 'From shared registration',
                'body' => 'Forwarded the QR content, ordered by the user publicId',
            ],
            'vaultRead' => [
                'title' => 'From vault read',
                'body' => 'Forwarded the QR content, ordered by the user publicId',
            ],   
            'vaultEdit' => [
                'title' => 'From vault edit',
                'body' => 'Forwarded the QR content, ordered by the user publicId',
            ],    
            'vaultDelete' => [
                'title' => 'From vault delete',
                'body' => 'Forwarded the QR content, ordered by the user publicId',
            ],                                 
        ];

        if($userPublicId)
        {                
            $this->firebaseService->manageFcm(  
                $userPublicId,                 
                $descriptions[$source]['title'],
                $descriptions[$source]['body'],
                $qrContent
            );               
        }
    }

    // PublicId in bridge is an vuln.
    public function getUserEmailByTargetId(array $source = []): ?string
    {       
        $this->logger->critical('Found targetId: ' . json_encode($source));
        if (isset($source['domainList'][0]) ) {

            $targetId = $first['targetId'] ?? null;            
            $publicId = $this->accessRegistryRepository->findOneBy(['targetId' => $targetId]);
            $identity = $this->identityRepository->findOneBy(['publicId' => $publicId]);
            if ($identity) {
                $this->logger->critical('Found identity by publicId: ' . $publicId);
                $this->logger->critical('Identity email (encrypted): ' . json_encode($identity));
                $email = $this->crypterDatabaseIdentityService->decryptData($identity->getEmail(), $identity->getIvEmail());
                return $email;
            }
        }

        return null;
    }   
}
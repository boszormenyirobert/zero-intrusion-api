<?php

namespace App\Controller\User;

use App\Service\Crypters\CrypterService;
use App\Helper\AuthorizationHelper;
use App\Repository\CorporateIdentityRepository;
use Psr\Log\LoggerInterface;
use App\Service\Corporate\CorporateRegistrationDatabaseService;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use App\Entity\CorporateIdentity;
use App\Exception\CorporateRegistrationException;
use App\Service\QrService\QrService;
use App\DTO\QR\CorporateRegistrationDTO;
use App\Service\AuthBridge\AuthBridgeService;
use App\DTO\QR\UserLoginDTO;

class UserService
{
    public function __construct(
        private readonly ContainerBagInterface $params,
        private readonly CorporateRegistrationDatabaseService $corporateRegistrationDatabaseService,
        private readonly CorporateIdentityRepository $corporateIdentityRepository,
        private readonly CrypterService $crypterService,
        private readonly LoggerInterface $logger,
        private readonly QrService $qrService,
        private readonly AuthBridgeService $authBridgeService

    ) {}

    /**
     * Send identifier data-set to ProxyApi
     *
     * - Authorization: SERVICE_API_KEY + SERVICE_API_SECRET
     * - Encryption:    DATA_HASH_SECRET
     */
    public function getQrData($payload, $processKey): array
    {
        $identity = $this->authBridgeService->generateRequestIdentity($processKey);
       
        // Call qr-generate service
        switch ($processKey){
            case 'registrationProcessId' : $qrCodeContent = $this->getQrRegistrationContent($payload, $identity);break;
            case 'domainProcessId' : $qrCodeContent = $this->getQrLoginContent($payload, $identity);break;  
            default : $qrCodeContent = [];
        }

        $qrCode = $this->qrService->getQrCode($qrCodeContent);
        
        $extended = (array)$identity;
        $extended['qrCode'] = $qrCode;

        // Encrypt data to send to the ProxyApi
        $crypterInit = new CrypterService($this->params);
        $crypterInit->setData($extended);
        $encryptedData = $crypterInit->encryptData();

        // Build authorization headers and response
        $authHelperInit = new AuthorizationHelper(
            $this->params->get('SERVICE_API_KEY'),
            $this->params->get('SERVICE_API_SECRET'),
            $this->logger
        );

        $header = $authHelperInit->getAuthHeader($encryptedData);
        $iv64 = $authHelperInit->getIvBase64();
            $this->logger->info('iv64: ' . $iv64);

        return  [
        "defaultResponse" => $authHelperInit->buildResponse(
            $header,
            $encryptedData,
            $iv64
        ),
        "mobileResponse" => $qrCodeContent
        ];
    }

    private function getQrRegistrationContent($payload, $identity){
        $corporatePublicId = $payload['corporatePublicId'] ?? null;
        $corporateAuthentication = $payload['corporateAuthentication'] ?? null;

        // Authentication controll missing

        $this->logger->critical('ERROR '. 'TODO: only first element of corporateAuthentication used');

        $xExtensionAuthOne = $identity->getXExtensionAuthOne();
        $newCorporateRegistration = new CorporateRegistrationDTO();
        $newCorporateRegistration->setCorporateId($corporatePublicId);//incoming
        $newCorporateRegistration->setCorporateAuthentication($corporateAuthentication[0]); //incoming  => ERROR only first element
        $newCorporateRegistration->setDomain($payload['domain']); //incoming
        $newCorporateRegistration->setXExtensionAuthOne($xExtensionAuthOne); //additionalByApi used by MobileApp
        $newCorporateRegistration->setRegistrationProcessId($identity->getRegistrationProcessId());//additionalByApi
        $newCorporateRegistration->setType('system_hub_registration');     //additionalByApi
        $newCorporateRegistration->setIsNew('new');

        return $newCorporateRegistration;
    }

    private function getQrLoginContent($payload, $identity){
        $corporatePublicId = $payload['corporatePublicId'] ?? null;
        $corporateAuthentication = $payload['corporateAuthentication'] ?? null;
        $domain = $payload['domain'] ?? null;

        // Authentication controll missing

        $xExtensionAuthOne = $identity->getXExtensionAuthOne();
        $userLogin = new UserLoginDTO(
            $domain,
            $identity->getDomainProcessId(),
            $xExtensionAuthOne,
            'system_hub_login',
            $corporatePublicId,
            $corporateAuthentication,
            'corporate'
        );

        return $userLogin;
    }    

}

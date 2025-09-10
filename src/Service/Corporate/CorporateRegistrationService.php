<?php

namespace App\Service\Corporate;

use App\Service\Crypters\CrypterService;
use App\Helper\AuthorizationHelper;
use App\Repository\CorporateIdentityRepository;
use Psr\Log\LoggerInterface;
use App\Service\Corporate\CorporateRegistrationDatabaseService;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use App\Entity\CorporateIdentity;
use App\Exception\CorporateRegistrationException;
use App\Repository\BusinessServicesRepository;
use App\Service\Shared\RequestService;

class CorporateRegistrationService
{
    public function __construct(
        private readonly ContainerBagInterface $params,
        private readonly CorporateRegistrationDatabaseService $corporateRegistrationDatabaseService,
        private readonly IdentityService $identityService,
        private readonly CorporateIdentityRepository $corporateIdentityRepository,
        private readonly CrypterService $crypterService,
        private readonly LoggerInterface $logger,
        private RequestService $requestService,
        private BusinessServicesRepository $businessServicesRepository        
    ) {}


    public function getBusinessRegistration($data){
        $businessModel = $data['businessModel'];        
        $publicId = $data['publicId'];
        $scope = $data['scope'];


        $businessSubscription = $this->corporateRegistrationDatabaseService->generateBusinessService($businessModel);
        $this->corporateRegistrationDatabaseService->updateUserIdentity($publicId, $businessSubscription);

        // Encrypt data to send to the ProxyApi
        $crypterInit = new CrypterService($this->params);
        $crypterInit->setData((array)$businessSubscription);  

        $encryptedData = $crypterInit->encryptData();

        // Build authorization headers and response
        $authHelperInit = new AuthorizationHelper(
            $this->params->get('SERVICE_API_KEY'),
            $this->params->get('SERVICE_API_SECRET'),
            $this->logger
        );

        $header = $authHelperInit->getAuthHeader($encryptedData);
        $iv64 = $authHelperInit->getIvBase64();

        return $authHelperInit->buildResponse(
            $header,
            $encryptedData,
            $iv64
        );
    }

    /**
     * Send identifier data-set to ProxyApi
     *
     * - Authorization: SERVICE_API_KEY + SERVICE_API_SECRET
     * - Encryption:    DATA_HASH_SECRET
     */
    public function getSubscriptionData(array $data): array
    {
        $publicId = $data['publicId'];
        $scope = $data['scope'];     
        $businessModel = $data['businessModel'];

        // Prepare data content, save in the database. Encrypted only to save in database except the corporateId,
        // The return value is "not encrypted" 

        // corporateIdentity with/without business registration
        $this->identityService->initializeIdentity($businessModel, $publicId, $scope);
        $identity = $this->identityService->getIdentity();

        // Relation between user and corporations
        $this->corporateRegistrationDatabaseService->createUserCorporateRelation($publicId, $identity['corporate_id']);

        // Encrypt data to send to the ProxyApi
        $crypterInit = new CrypterService($this->params);
        $crypterInit->setData($identity);   

        $encryptedData = $crypterInit->encryptData();

        // Build authorization headers and response
        $authHelperInit = new AuthorizationHelper(
            $this->params->get('SERVICE_API_KEY'),
            $this->params->get('SERVICE_API_SECRET'),
            $this->logger
        );

        $header = $authHelperInit->getAuthHeader($encryptedData);
        $iv64 = $authHelperInit->getIvBase64();

        return $authHelperInit->buildResponse(
            $header,
            $encryptedData,
            $iv64
        );
    }

    public function updateSubscriptionData($corporateFollowUpData): CorporateIdentity|Array
    {
        try {      

            if (
                !isset($corporateFollowUpData['updateIdentity']['corporateId']) ||
                empty($corporateFollowUpData['updateIdentity']['corporateId'])
            ) {            
                return [
                    'error' => true,
                    'message' => 'CorporateId missing in the follow-up data'
                ]; 
            }

            $corporate = $this->corporateIdentityRepository->findOneBy([
                'corporateId' => $corporateFollowUpData['updateIdentity']['corporateId']
            ]);

            if (!$corporate) {
                return [
                    'error' => true,
                    'message' => 'CorporateId is not registrated in the database'
                ];           
            }

            return $this->corporateRegistrationDatabaseService->addFollowUpData($corporate, $corporateFollowUpData);
        } catch (CorporateRegistrationException $e) {
            return [
                'error' => true,
                'message' => $e->getMessage(),
                'data' => $e->getErrorData()
            ];
        } catch (\Throwable $e) {
            return [
                'error' => true,
                'message' => $e->getMessage()
            ];
        }
    }

    public function getSelectedSubscription($businessSubscriptionId){   
      $business = $this->businessServicesRepository->findOneBy(['id' => $businessSubscriptionId]);

        foreach((array)$business as $key => $value){
            if($value === true){                
                return $key;
            }
        }        
    }

    public function accessDataByKey($payload, $key)
    {   
        $validatedPayload = $this->requestService->validPayload($payload);
        $dataJson = $validatedPayload[$key];

        return json_decode($dataJson, true);
    }    
}

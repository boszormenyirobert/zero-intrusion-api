<?php
/**
 * To register a new Corporate account, the user must create a Business service as prerequisite
 * After that, he can create multiple Corporate identities under that Business service in the Account section
 */
namespace App\Controller\Corporate;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\Corporate\CorporateRegistrationService;
use App\Service\Shared\RequestService;
use Psr\Log\LoggerInterface;
use App\Attribute\RequireHmac;
use App\Attribute\RequireJson;
use App\Repository\IdentityRepository;

#[Route('/api/registration/corporate')]
class CorporateRegistrationController extends AbstractController
{
    public function __construct(
        private CorporateRegistrationService $corporateRegistrationService,
        private LoggerInterface $logger,
        private RequestService $requestService
    ) {    }

    /**
     * Create a new Business service as a prerequisite for Corporate identities
     */
    #[Route('/business/create', name: 'service_registration_business_Create', methods: ['POST'])]
    #[RequireHmac]
    #[RequireJson]
    public function businessCreate(Request $request): Response    {
        $payload = $this->requestService->requestControll($request);
        $data = $this->corporateRegistrationService->accessDataByKey($payload, 'business_create');

        if (array_key_exists('error', $payload)) {
            $this->logger->critical('service_registration_corporate_data: ' . json_encode($payload));
            return $this->json($payload);
        }
        $identityConfig = $this->corporateRegistrationService->getBusinessRegistration($data);
        
        return new Response($identityConfig['body'], 200, $identityConfig['headers']);
    }

    /**
     * Initialize Corporate identity creation under an existing Business service
     * First step of the Corporate registration process creating the corporate identity
     */
    #[Route('/identity/create/initialize', name: 'service_registration_corporate_data', methods: ['POST'])]
    #[RequireHmac]
    #[RequireJson]
    public function serviceIdentity(
        Request $request,
        IdentityRepository $identityRepository
        ): Response    
    {
        $payload = $this->requestService->requestControll($request);
        $data = $this->corporateRegistrationService->accessDataByKey($payload, 'getIdentity');        

        if($data['scope'] == 'external'){
            $identity = $identityRepository->findOneBy(['publicId' => $data['publicId']]);
            $data['businessModel'] = $this->corporateRegistrationService->getSelectedSubscription($identity->getBusinessService());            
        } 

        if (array_key_exists('error', $payload)) {
            $this->logger->critical('ERROR service_registration_corporate_data: ' . json_encode($payload));
            return $this->json($payload);
        }

        $identityConfig = $this->corporateRegistrationService->getSubscriptionData($data);
        return new Response($identityConfig['body'], 200, $identityConfig['headers']);
    }

    /**
     * Finalize Corporate identity creation under an existing Business service
     * Second step of the Corporate registration process updating the corporate identity with the call-back data and extend data-set
     */
    #[Route('/identity/create/follow-up', name: 'service_registration_corporate_data_extend', methods: ['POST'])]
    #[RequireHmac]
    #[RequireJson]
    public function serviceRegistration(Request $request): Response
    {
        $payload = $this->requestService->requestControll($request);

        if (array_key_exists('error', $payload)) {
            return $this->json($payload);        }
       
        $validatedPayload = $this->requestService->validPayload($payload);
        $updatedCorporate = $this->corporateRegistrationService->updateSubscriptionData($validatedPayload);
 
        if (is_array($updatedCorporate) && array_key_exists('error', $updatedCorporate)) {

            return $this->json($updatedCorporate);
        }
        return new Response(true);
    }
}
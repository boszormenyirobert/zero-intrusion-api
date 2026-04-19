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
        $this->logger->info('Corporate initialize request received.', [
            'route' => 'service_registration_corporate_data',
            'path' => $request->getPathInfo(),
            'has_auth_header' => $request->headers->has('X-Auth'),
            'content_length' => strlen((string) $request->getContent()),
        ]);

        $payload = $this->requestService->requestControll($request);

        if (array_key_exists('error', $payload)) {
            $this->logger->critical('Corporate initialize request rejected during request validation.', [
                'route' => 'service_registration_corporate_data',
                'error' => $payload,
            ]);

            return $this->json($payload);
        }

        $data = $this->corporateRegistrationService->accessDataByKey($payload, 'getIdentity');        

        $this->logger->info('Corporate initialize payload resolved.', [
            'route' => 'service_registration_corporate_data',
            'public_id' => $data['publicId'] ?? null,
            'scope' => $data['scope'] ?? null,
            'has_business_model' => array_key_exists('businessModel', $data),
        ]);

        if($data['scope'] == 'external'){
            $this->logger->info('Corporate initialize request uses external scope. Resolving business subscription from identity.', [
                'route' => 'service_registration_corporate_data',
                'public_id' => $data['publicId'] ?? null,
            ]);

            $identity = $identityRepository->findOneBy(['publicId' => $data['publicId']]);
            // Bug in the logic. The logged-in user is not forced to be the owner of any Business service
            $data['businessModel'] = $this->corporateRegistrationService->getSelectedSubscription($identity->getBusinessService());            

            $this->logger->info('Corporate initialize external scope resolved business model.', [
                'route' => 'service_registration_corporate_data',
                'public_id' => $data['publicId'] ?? null,
                'business_model' => $data['businessModel'] ?? null,
            ]);
        } 

        $identityConfig = $this->corporateRegistrationService->getSubscriptionData($data);

        $this->logger->info('Corporate initialize response prepared.', [
            'route' => 'service_registration_corporate_data',
            'public_id' => $data['publicId'] ?? null,
            'scope' => $data['scope'] ?? null,
            'response_header_keys' => array_keys($identityConfig['headers'] ?? []),
            'response_body_length' => strlen((string) ($identityConfig['body'] ?? '')),
        ]);

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
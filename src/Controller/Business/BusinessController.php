<?php
/**
 * To register a new Corporate account, the user must create a Business service as prerequisite
 */
namespace App\Controller\Business;

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
class BusinessController extends AbstractController
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
}
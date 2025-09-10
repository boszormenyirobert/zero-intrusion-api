<?php
/**
 * SERVICE_API_KEY, SERVICE_API_SECRET, DATA_HASH_SECRET ex-changed between easylogin and ProxyApi
 * 
 */
namespace App\Controller\Account;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\Corporate\CorporateRegistrationService;
use App\Service\Shared\RequestService;
use Psr\Log\LoggerInterface;
use App\Attribute\RequireHmac;
use App\Attribute\RequireJson;
use App\Repository\CorporateIdentityRepository;
use App\Repository\UserRegistratedCorporateRepository;
use App\Repository\IdentityRepository;


#[Route('/api/account')]
class AccountController extends AbstractController
{
    public function __construct(
        private CorporateRegistrationService $corporateRegistrationService,
        private LoggerInterface $logger,
        private RequestService $requestService
    ) {    }


    #[Route('/all', name: 'account', methods: ['POST'])]
    public function account(
        Request $request,
        CorporateIdentityRepository $corporateIdentityRepository,
        UserRegistratedCorporateRepository $userRegistratedCorporateRepository,
        IdentityRepository $identityRepository
        ): Response    {
        $payload = $this->requestService->requestControll($request);
        $validatedPayload = $this->requestService->validPayload($payload);
        $payloadArray = $validatedPayload['get_registrated_business'];
        
        $userPublicId = $payloadArray['publicId'];
        $email = $payloadArray['email'];


        $userBusinessData = $identityRepository->findOneBy([
            'publicId' =>  $userPublicId
        ]);


        $businessId = $userBusinessData->getBusinessService();
        $corporates = $corporateIdentityRepository->findBy([
            'businessServices' => $businessId
        ]);


        return $this->json(
            [
                'accounts' => $corporates,
                'businessSubscription' => $userBusinessData->getBusinessService()
            ]);
    }
}
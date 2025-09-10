<?php

namespace App\Service\Corporate;

use Doctrine\ORM\EntityManagerInterface;
use App\Entity\CorporateIdentity;
use Psr\Log\LoggerInterface;
use App\Entity\BusinessServices;
use App\Entity\UserRegistratedCorporate;
use App\Repository\IdentityRepository;

class CorporateRegistrationDatabaseService
{

    public function __construct(
        private EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        private IdentityRepository $identityRepository
    ) {  }

    public function generateBusinessService($businessModel){
        $businessServices = new BusinessServices();

        switch($businessModel){
            case 'businessPro' : {
                $businessServices->setPro(true);
                $businessServices->setPlus(false);
                $businessServices->setBasic(false);
                $businessServices->setBiometric(false);
                $businessServices->setPasswordManager(false);
                break;
            }
            case 'businessPlus' : {                
                $businessServices->setPro(false);
                $businessServices->setPlus(true);
                $businessServices->setBasic(false);
                $businessServices->setBiometric(false);
                $businessServices->setPasswordManager(false);
                break;
            }
            case 'businessBasic' : {                
                $businessServices->setPro(false);
                $businessServices->setPlus(false);
                $businessServices->setBasic(true);
                $businessServices->setBiometric(false);
                $businessServices->setPasswordManager(false);
                break;
            }          
            case 'biometric' : {                
                $businessServices->setPro(false);
                $businessServices->setPlus(false);
                $businessServices->setBasic(false);
                $businessServices->setBiometric(true);
                $businessServices->setPasswordManager(false);
                break;
            } 
            case 'passwordManager' : {                
                $businessServices->setPro(false);
                $businessServices->setPlus(false);
                $businessServices->setBasic(false);
                $businessServices->setBiometric(false);
                $businessServices->setPasswordManager(true);
                break;
            }                           
        }

        $this->entityManager->persist($businessServices);
        $this->entityManager->flush();

        return $businessServices;
    }

    public function addNewIdentity(CorporateIdentity $corporateIdentity, $businessModel, $publicId, $scope): void
    {
        if($scope === 'internal'){
            $businessServices = $this->generateBusinessService($businessModel);
            $corporateIdentity->setBusinessServices($businessServices);
            $this->updateUserIdentity($publicId, $businessServices);
        }else if($scope === 'external'){
            $identity = $this->identityRepository->findOneBy([
                'publicId' => $publicId
            ]);                   
            $corporateIdentity->setBusinessServices($identity->getBusinessService());
        }

        try {
            $this->entityManager->persist($corporateIdentity);
            $this->entityManager->flush();
        } catch (\Throwable $e) {
             $this->logger->critical('--------------Error' . json_encode((array)$e));
            throw $e;
        }
    }

    public function addFollowUpData(CorporateIdentity $corporateIdentity, $followUpDecryptedCorporateData): CorporateIdentity|Array
    {
        try {
            $corporateIdentity->setCallbackUserLogin($followUpDecryptedCorporateData['updateIdentity']['callbackUserLogin']);
            $corporateIdentity->setCallbackUserRegistration($followUpDecryptedCorporateData['updateIdentity']['callbackUserRegistration']);
            $corporateIdentity->setDomain($followUpDecryptedCorporateData['updateIdentity']['domain']);
            
            $this->entityManager->flush();
            return $corporateIdentity;
        } catch (\Throwable $e) {
            return [
                'error' => true,
                'message' => 'Corporate not saved in database'
            ];             
        }
    }

    public function createUserCorporateRelation($publicId, $corporateId){
        $newCorporateRegistration = new UserRegistratedCorporate();
        $newCorporateRegistration->setPublicId($publicId);
        $newCorporateRegistration->setCorporateId($corporateId);

        $this->entityManager->persist($newCorporateRegistration);
        $this->entityManager->flush();
    }

    public function updateUserIdentity($publicId, $businessServices)
    {
        $identity = $this->identityRepository->findOneBy([
            'publicId' => $publicId
        ]);

        $identity->setBusinessService($businessServices);

        $this->entityManager->persist($identity);
        $this->entityManager->flush();
    }
}

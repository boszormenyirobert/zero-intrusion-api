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
        $this->logger->info('CorporateRegistrationDatabaseService generateBusinessService started.', [
            'business_model' => $businessModel,
        ]);

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

        $this->logger->info('CorporateRegistrationDatabaseService generateBusinessService persisted business service.', [
            'business_model' => $businessModel,
            'business_service_id' => $businessServices->getId(),
        ]);

        return $businessServices;
    }

    public function addNewIdentity(CorporateIdentity $corporateIdentity, $businessModel, $publicId, $scope): void
    {
        $this->logger->info('CorporateRegistrationDatabaseService addNewIdentity started.', [
            'public_id' => $publicId,
            'scope' => $scope,
            'business_model' => $businessModel,
            'corporate_id' => $corporateIdentity->getCorporateId(),
        ]);

        if($scope === 'internal'){
            $businessServices = $this->generateBusinessService($businessModel);

            $this->logger->info('CorporateRegistrationDatabaseService addNewIdentity generated internal business service.', [
                'public_id' => $publicId,
                'corporate_id' => $corporateIdentity->getCorporateId(),
                'business_service_id' => $businessServices->getId(),
            ]);

            $corporateIdentity->setBusinessServices($businessServices);
            $this->logger->info('CorporateRegistrationDatabaseService addNewIdentity assigned business service to corporate identity.', [
                'public_id' => $publicId,
                'corporate_id' => $corporateIdentity->getCorporateId(),
                'business_service_id' => $businessServices->getId(),
            ]);

            // TODO : should not called by the FE HUB registration: updateUserIdentity
            $this->logger->info('CorporateRegistrationDatabaseService addNewIdentity calling updateUserIdentity.', [
                'public_id' => $publicId,
                'corporate_id' => $corporateIdentity->getCorporateId(),
                'business_service_id' => $businessServices->getId(),
            ]);

            $this->updateUserIdentity($publicId, $businessServices);

            $this->logger->info('CorporateRegistrationDatabaseService addNewIdentity updateUserIdentity returned.', [
                'public_id' => $publicId,
                'corporate_id' => $corporateIdentity->getCorporateId(),
                'business_service_id' => $businessServices->getId(),
            ]);

            $this->logger->info('CorporateRegistrationDatabaseService addNewIdentity linked internal business service.', [
                'public_id' => $publicId,
                'corporate_id' => $corporateIdentity->getCorporateId(),
                'business_service_id' => $businessServices->getId(),
            ]);
        }else if($scope === 'external'){
            $identity = $this->identityRepository->findOneBy([
                'publicId' => $publicId
            ]);                   
            $corporateIdentity->setBusinessServices($identity->getBusinessService());

            $this->logger->info('CorporateRegistrationDatabaseService addNewIdentity linked external business service.', [
                'public_id' => $publicId,
                'corporate_id' => $corporateIdentity->getCorporateId(),
                'business_service_id' => $identity->getBusinessService()?->getId(),
            ]);
        }

        try {
            $this->entityManager->persist($corporateIdentity);
            $this->entityManager->flush();

            $this->logger->info('CorporateRegistrationDatabaseService addNewIdentity persisted corporate identity.', [
                'public_id' => $publicId,
                'corporate_id' => $corporateIdentity->getCorporateId(),
                'scope' => $scope,
            ]);
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
        $this->logger->info('CorporateRegistrationDatabaseService createUserCorporateRelation started.', [
            'public_id' => $publicId,
            'corporate_id' => $corporateId,
        ]);

        $newCorporateRegistration = new UserRegistratedCorporate();
        $newCorporateRegistration->setPublicId($publicId);
        $newCorporateRegistration->setCorporateId($corporateId);

        $this->entityManager->persist($newCorporateRegistration);
        $this->entityManager->flush();

        $this->logger->info('CorporateRegistrationDatabaseService createUserCorporateRelation persisted relation.', [
            'public_id' => $publicId,
            'corporate_id' => $corporateId,
        ]);
    }

    public function updateUserIdentity($publicId, $businessServices)
    {
        $this->logger->info('CorporateRegistrationDatabaseService updateUserIdentity started.', [
            'public_id' => $publicId,
            'business_service_id' => $businessServices?->getId(),
        ]);

        $this->logger->info('CorporateRegistrationDatabaseService updateUserIdentity querying identity by public id.', [
            'public_id' => $publicId,
        ]);

        $identity = $this->identityRepository->findOneBy([
            'publicId' => $publicId
        ]);


        if ($identity === null) {
            $this->logger->critical('CorporateRegistrationDatabaseService updateUserIdentity identity not found for public id.', [
                'public_id' => $publicId,
                'new_business_service_id' => $businessServices?->getId(),
            ]);
        }

        $identity->setBusinessService($businessServices);
        $this->entityManager->persist($identity);
        $this->entityManager->flush();
    }
}

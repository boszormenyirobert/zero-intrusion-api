<?php

declare(strict_types=1);

namespace App\Service\Corporate;

use App\Entity\BusinessServices;
use App\Entity\CorporateIdentity;
use App\Entity\Identity;
use App\Entity\UserRegistratedCorporate;
use App\Repository\IdentityRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class CorporateRegistrationDatabaseService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
        private readonly IdentityRepository $identityRepository,
        private readonly ?CorporateBusinessStateConfigurator $corporateBusinessStateConfigurator = null,
        private readonly ?CorporateFollowUpDataApplier $corporateFollowUpDataApplier = null,
    ) {
    }

    public function generateBusinessService(string $businessModel): BusinessServices
    {
        $this->logger->info('CorporateRegistrationDatabaseService generateBusinessService started.', [
            'business_model' => $businessModel,
        ]);

        $businessServices = new BusinessServices();
        $this->businessStateConfigurator()->apply($businessServices, $businessModel);
        $this->persistAndFlush($businessServices);

        $this->logger->info('CorporateRegistrationDatabaseService generateBusinessService persisted business service.', [
            'business_model' => $businessModel,
            'business_service_id' => $businessServices->getId(),
        ]);

        return $businessServices;
    }

    public function addNewIdentity(CorporateIdentity $corporateIdentity, string $businessModel, string $publicId, string $scope): void
    {
        $this->logger->info('CorporateRegistrationDatabaseService addNewIdentity started.', [
            'public_id' => $publicId,
            'scope' => $scope,
            'business_model' => $businessModel,
            'corporate_id' => $corporateIdentity->getCorporateId(),
        ]);

        if ($scope === 'internal') {
            $this->attachInternalBusinessService($corporateIdentity, $businessModel, $publicId);
        } elseif ($scope === 'external') {
            $this->attachExternalBusinessService($corporateIdentity, $publicId);
        }

        try {
            $this->persistAndFlush($corporateIdentity);

            $this->logger->info('CorporateRegistrationDatabaseService addNewIdentity persisted corporate identity.', [
                'public_id' => $publicId,
                'corporate_id' => $corporateIdentity->getCorporateId(),
                'scope' => $scope,
            ]);
        } catch (\Throwable $e) {
            $this->logger->critical('CorporateRegistrationDatabaseService addNewIdentity failed.', [
                'public_id' => $publicId,
                'corporate_id' => $corporateIdentity->getCorporateId(),
                'scope' => $scope,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function addFollowUpData(CorporateIdentity $corporateIdentity, array $followUpDecryptedCorporateData): CorporateIdentity
    {
        try {
            $this->followUpDataApplier()->apply($corporateIdentity, $followUpDecryptedCorporateData);
            $this->entityManager->flush();

            return $corporateIdentity;
        } catch (\Throwable $e) {
            throw new \RuntimeException('Corporate not saved in database', 0, $e);
        }
    }

    public function createUserCorporateRelation(string $publicId, string $corporateId): void
    {
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

    public function updateUserIdentity(string $publicId, BusinessServices $businessServices): void
    {
        $this->logger->info('CorporateRegistrationDatabaseService updateUserIdentity started.', [
            'public_id' => $publicId,
            'business_service_id' => $businessServices?->getId(),
        ]);

        $this->logger->info('CorporateRegistrationDatabaseService updateUserIdentity querying identity by public id.', [
            'public_id' => $publicId,
        ]);

        $identity = $this->resolveIdentityByPublicId($publicId, $businessServices);

        $identity->setBusinessService($businessServices);
        $this->persistAndFlush($identity);
    }

    private function attachInternalBusinessService(CorporateIdentity $corporateIdentity, string $businessModel, string $publicId): void
    {
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
    }

    private function attachExternalBusinessService(CorporateIdentity $corporateIdentity, string $publicId): void
    {
        $identity = $this->resolveIdentityByPublicId($publicId);
        $corporateIdentity->setBusinessServices($identity->getBusinessService());

        $this->logger->info('CorporateRegistrationDatabaseService addNewIdentity linked external business service.', [
            'public_id' => $publicId,
            'corporate_id' => $corporateIdentity->getCorporateId(),
            'business_service_id' => $identity->getBusinessService()?->getId(),
        ]);
    }

    private function resolveIdentityByPublicId(string $publicId, ?BusinessServices $businessServices = null): Identity
    {
        $identity = $this->identityRepository->findOneBy([
            'publicId' => $publicId,
        ]);

        if ($identity instanceof Identity) {
            return $identity;
        }

        $this->logger->critical('CorporateRegistrationDatabaseService updateUserIdentity identity not found for public id.', [
            'public_id' => $publicId,
            'new_business_service_id' => $businessServices?->getId(),
        ]);

        throw new \RuntimeException('Identity not found.');
    }

    private function persistAndFlush(object $entity): void
    {
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }

    private function businessStateConfigurator(): CorporateBusinessStateConfigurator
    {
        return $this->corporateBusinessStateConfigurator ?? new CorporateBusinessStateConfigurator();
    }

    private function followUpDataApplier(): CorporateFollowUpDataApplier
    {
        return $this->corporateFollowUpDataApplier ?? new CorporateFollowUpDataApplier();
    }
}

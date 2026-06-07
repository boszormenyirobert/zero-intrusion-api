<?php

declare(strict_types=1);

namespace App\Service\AccessRegistry\CredentialHubResolver;

use App\Repository\AccessRegistryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class DeleteService
{
    public function __construct(
        private readonly AccessRegistryRepository $accessRegistryRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {}

    public function deleteAccessRegistry(string $targetId): bool
    {
        $accessRegistry = $this->accessRegistryRepository->findOneBy([
            'targetId' => $targetId
        ]);

        if ($accessRegistry) {
            $this->entityManager->remove($accessRegistry);
            $this->entityManager->flush();

            return true;
        }

        return false;
    }

    public function deleteUserDomainCombination(array $user, array $collecion): void
    {
        $newCombination = true;
        $existingPages = [];

        foreach ($collecion as $page) {

            if ($page['decrypted']->getTargetId() === $user['targetId']
            ) {
            $this->logger->info('------------------STEP 2.2.0'. $page['encrypted']->getPublicId());
            $this->logger->info('------------------STEP 2.2.0'. $page['decrypted']->getTargetId());
            }

            if ($page['encrypted']->getPublicId() === $user['publicId'] && 
            //    $page['decrypted']->getDomain() === $user['domain'] &&
                $page['decrypted']->getTargetId() === $user['targetId']
            ) {
                $newCombination = false;
                $deleteUserItem = $this->accessRegistryRepository->findOneBy(
                    [
              //          'domain' => $page['encrypted']->getDomain(),
                        'publicId' => $page['encrypted']->getPublicId(),
                        'targetId' => $page['encrypted']->getTargetId()
                    ]
                );
                $this->logger->info('------------------STEP 2.2');
                $this->entityManager->remove($deleteUserItem);
                $this->entityManager->flush();
            }
        }
    }    
}
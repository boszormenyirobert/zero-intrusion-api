<?php

namespace App\Service\AccessRegistry\CredentialHubResolver;

use Doctrine\ORM\EntityManagerInterface;
use App\Repository\AccessRegistryRepository;
use Psr\Log\LoggerInterface;

class DeleteService
{
    public function __construct(
        private AccessRegistryRepository $accessRegistryRepository,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
    ) {}

    public function deleteAccessRegistry(string $targetId):bool{
        
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

    public function deleteUserDomainCombination(array $user, $collecion)
    {
        $newCombination = true;

        $existingPages = [];

        foreach ($collecion as $page) {

            if ($page['encrypted']->getPublicId() === $user['publicId'] && $page['decrypted']->getDomain() === $user['domain']) {
                $newCombination = false;
                $deleteUserItem = $this->accessRegistryRepository->findOneBy(
                    [
                        'domain' => $page['encrypted']->getDomain(),
                        'publicId' => $page['encrypted']->getPublicId()
                    ]
                );
                $this->entityManager->remove($deleteUserItem);
                $this->entityManager->flush();
            }
        }

        return [
            "newCombination" => $newCombination,
            "existingPage" => $existingPages
        ];
    }    
}
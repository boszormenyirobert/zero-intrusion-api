<?php

declare(strict_types=1);

namespace App\Service\CredentialHub\Vault\Read;

use App\Repository\AccessRegistryRepository;
use App\Service\AccessRegistry\Database\CrypterDatabaseAccessRegistryService;
use App\Service\CredentialHub\Shared\ReadCredentialDecryptedStrategyInterface;
use Psr\Log\LoggerInterface;

class VaultReadCredentialDecryptedStrategy implements ReadCredentialDecryptedStrategyInterface
{
    public function __construct(
        private readonly AccessRegistryRepository $accessRegistryRepository,
        private readonly CrypterDatabaseAccessRegistryService $crypterDatabaseAccessRegistryService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function resolve(array $context): array
    {
        $publicId = (string) ($context['publicId'] ?? '');
        $applicationList = $this->getApplicationCreadentials($publicId);

        return [
            'credentials' => $applicationList,
            'publicKey' => $context['publicKey'] ?? 'missing',
            'validation' => true,
            'sessionId' => $context['sessionId'] ?? 'missing',
        ];
    }

    public function getApplicationCreadentials(string $userPublicId): array
    {
        if ($userPublicId === '') {
            $this->logger->warning('User public ID is empty. Skipping credential retrieval.');

            return [];
        }

        $applicationList = [];
        $pages = $this->accessRegistryRepository->findUnassignedApplicationsByPublicId($userPublicId);

        foreach ($pages as $userPage) {
            if ($userPage->getApplication() === null) {
                continue;
            }

            $decrypted = $this->crypterDatabaseAccessRegistryService->decryptFromDatabaseOrFail($userPage, 'application');

            $applicationList[] = [
                'application' => $decrypted->getApplication(),
                'credential' => $decrypted->getUserCredential(),
                'description' => $decrypted->getDescription(),
                'targetId' => $decrypted->getTargetId(),
            ];
        }

        return $applicationList;
    }
}

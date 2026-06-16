<?php

declare(strict_types=1);

namespace App\Service\CredentialHub;

use App\Repository\AccessRegistryRepository;
use App\Repository\IdentityRepository;
use App\Service\Firebase\FirebaseService;
use App\Service\Identity\Database\CrypterDatabaseIdentityService;
use Psr\Log\LoggerInterface;
use App\Service\CredentialHub\IdentityType;


class SharedNotificationService
{
    /** @var array<string, array{title: string, body: string}> */
    private const FCM_DESCRIPTIONS = [
        'domainDelete' => [
            'title' => 'From domain delete',
            'body' => 'Forwarded the QR content, ordered by the user publicId',
        ],
        'domainRead' => [
            'title' => 'From domain read',
            'body' => 'Forwarded the QR content, ordered by the user publicId',
        ],
        'newUserCredential' => [
            'title' => 'From shared registration',
            'body' => 'Forwarded the QR content, ordered by the user publicId',
        ],
        'vaultRead' => [
            'title' => 'From vault read',
            'body' => 'Forwarded the QR content, ordered by the user publicId',
        ],
        'vaultEdit' => [
            'title' => 'From vault edit',
            'body' => 'Forwarded the QR content, ordered by the user publicId',
        ],
        'vaultDelete' => [
            'title' => 'From vault delete',
            'body' => 'Forwarded the QR content, ordered by the user publicId',
        ],
    ];

    public function __construct(
        private readonly FirebaseService $firebaseService,
        private readonly IdentityRepository $identityRepository,
        private readonly AccessRegistryRepository $accessRegistryRepository,
        private readonly CrypterDatabaseIdentityService $crypterDatabaseIdentityService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function sendFcmNotification(string $type, ?string $userPublicId, mixed $qrContent, bool $throwOnFailure = false): bool
    {
        if ($userPublicId === null || $userPublicId === '') {
            return false;
        }

        if (!isset(self::FCM_DESCRIPTIONS[$type])) {
            return false;
        }

        $description = self::FCM_DESCRIPTIONS[$type];
        $delivered = $this->firebaseService->manageFcm($userPublicId, $description['title'], $description['body'], $qrContent);

        if (!$delivered && $throwOnFailure) {
            throw new \RuntimeException(sprintf('FCM delivery failed for type "%s" and user "%s".', $type, $userPublicId));
        }

        return $delivered;
    }

    /**
     * @return array{email: ?string, publicId: ?string}
     */
    public function getUserEmailByTargetId(array $source = []): array
    {
        if (!isset($source['response'][0])) {
            return ['email' => null, 'publicId' => null];
        }

        $targetId = $source['response'][0]['targetId'] ?? null;
        $user = $targetId !== null ? $this->accessRegistryRepository->findOneBy(['targetId' => $targetId]) : null;

        try {
            if ($user !== null) {
                $identity = $this->identityRepository->findOneBy(['publicId' => $user->getPublicId()]);

                if ($identity !== null) {
                    $decryptedIdentity = $this->crypterDatabaseIdentityService->decryptFromDatabaseDevice($identity);

                    return [
                        'email' => $decryptedIdentity->getEmail(),
                        'publicId' => $decryptedIdentity->getPublicId(),
                    ];
                }
            }
        } catch (\Exception $exception) {
            $this->logger->error('User email lookup failed.', [
                'source' => $source,
                'exceptionClass' => $exception::class,
                'exceptionMessage' => $exception->getMessage(),
            ]);
        }

        return ['email' => null, 'publicId' => null];
    }
}
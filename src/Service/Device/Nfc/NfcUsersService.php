<?php

declare(strict_types=1);

namespace App\Service\Device\Nfc;

use App\Entity\Identity;
use App\Repository\IdentityRepository;
use App\Service\Crypters\CrypterDatabaseLoginService;
use App\Service\Crypters\SodiumService;
use Psr\Log\LoggerInterface;

class NfcUsersService
{
    public function __construct(
        private readonly IdentityRepository $identityRepository,
        private readonly CrypterDatabaseLoginService $crypterDatabaseLoginService,
        private readonly SodiumService $sodiumService,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function handle(): array
    {
        $usersEncrypted = $this->identityRepository->findAll();
        $users = [];

        foreach ($usersEncrypted as $identity) {
            try {
                $decryptedUser = $this->crypterDatabaseLoginService->decryptFromDatabaseidentity($identity);
                $encryptedUserData = $this->encryptUserForNfc($decryptedUser, (string) $identity->getNfcEncryptionKey());

                $users[] = $this->buildUserResponse($decryptedUser, $encryptedUserData);
            } catch (\Exception $exception) {
                $this->logger->critical('NFC USERS ENCRYPTION ERROR ' . $exception->getMessage());
            }
        }

        return ['users' => $users];
    }

    private function encryptUserForNfc(Identity $decryptedUser, string $nfcEncryptionKey): string
    {
        $payload = json_encode($this->buildRawUserData($decryptedUser), JSON_THROW_ON_ERROR);

        return $this->sodiumService->sodiumEncrypt($payload, $nfcEncryptionKey);
    }

    /**
     * @return array{puID: ?string, prID: string, secret: ?string, credSecret: ?string}
     */
    private function buildRawUserData(Identity $decryptedUser): array
    {
        return [
            'puID' => $decryptedUser->getPublicId(),
            'prID' => $this->sodiumService->sodiumDecrypt((string) $decryptedUser->getPrivateId(), (string) $decryptedUser->getSecret()),
            'secret' => $decryptedUser->getSecret(),
            'credSecret' => $decryptedUser->getCredentialSecret(),
        ];
    }

    /**
     * @return array{email: ?string, nfcData: string, puID: ?string}
     */
    private function buildUserResponse(Identity $decryptedUser, string $encryptedUserData): array
    {
        return [
            'email' => $decryptedUser->getEmail(),
            'nfcData' => $encryptedUserData,
            'puID' => $decryptedUser->getPublicId(),
        ];
    }
}

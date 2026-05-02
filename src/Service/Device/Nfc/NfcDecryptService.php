<?php

declare(strict_types=1);

namespace App\Service\Device\Nfc;

use App\DTO\Device\Nfc\NfcDecryptRequestDTO;
use App\Repository\IdentityRepository;
use App\Service\Crypters\SodiumService;
use App\Service\Payload\JsonPayloadDecoder;

class NfcDecryptService
{
    public function __construct(
        private readonly IdentityRepository $identityRepository,
        private readonly SodiumService $sodiumService,
        private readonly JsonPayloadDecoder $jsonPayloadDecoder,
    ) {
    }

    public function handle(NfcDecryptRequestDTO $request): array
    {
        $identity = $this->identityRepository->findOneBy(['publicId' => $request->userPublicId]);
        $nfcEncryptionKey = $identity?->getNfcEncryptionKey() ?? '';

        $decryptedUserDataJson = $this->sodiumService->sodiumDecrypt((string) $request->nfcData, $nfcEncryptionKey);

        return $this->jsonPayloadDecoder->decodeArray((string) $decryptedUserDataJson) ?? [];
    }
}

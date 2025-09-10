<?php

namespace App\Service\AuthBridge\AuthBridgeHandler\Application;

use App\Service\Crypters\CrypterDatabaseLoginService;
use Symfony\Component\Serializer\SerializerInterface;

class Encryptor
{
    public function __construct(
        private CrypterDatabaseLoginService $crypterDatabaseLoginService,
        private SerializerInterface $serializer
    ) {}

    public function encrypt(array $applicationList, string $iv): string
    {
        return $this->crypterDatabaseLoginService->encryptData(
            $this->serializer->serialize($applicationList, 'json'),
            $iv
        );
    }
}
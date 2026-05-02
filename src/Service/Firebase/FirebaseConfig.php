<?php

declare(strict_types=1);

namespace App\Service\Firebase;

final readonly class FirebaseConfig
{
    public function __construct(
        private string $projectId,
        private string $clientEmail,
        private string $privateKey,
        private string $tokenUri,
        private string $caCertPath,
    ) {
    }

    public function getProjectId(): string
    {
        return $this->projectId;
    }

    public function getClientEmail(): string
    {
        return $this->clientEmail;
    }

    public function getPrivateKey(): string
    {
        return $this->privateKey;
    }

    public function getTokenUri(): string
    {
        return $this->tokenUri;
    }

    public function getCaCertPath(): string
    {
        return $this->caCertPath;
    }
}

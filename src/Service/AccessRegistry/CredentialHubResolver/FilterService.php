<?php

declare(strict_types=1);

namespace App\Service\AccessRegistry\CredentialHubResolver;

use App\Repository\AccessRegistryRepository;

final class FilterService
{
    public function __construct(
        private readonly AccessRegistryRepository $accessRegistryRepository
    ) {}

    public function getUserRegistratedPages(array $user, string $key): array
    {
        $collection = $this->accessRegistryRepository->findBy([
            'publicId' => $user['publicId']
        ]);

        if (empty($collection)) {
            return [];
        }

        if ($key == 'application') {
            return array_filter($collection, static function ($data) {
                return $data->getApplication();
            });
        }

        if ($key == 'domain') {
            return array_filter($collection, static function ($data) {
                return $data->getDomain();
            });
        }

        return [];
    }
}
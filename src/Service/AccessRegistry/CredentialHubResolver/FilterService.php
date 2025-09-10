<?php

namespace App\Service\AccessRegistry\CredentialHubResolver;

use App\Repository\AccessRegistryRepository;

final class FilterService
{
    public function __construct(
        private AccessRegistryRepository $accessRegistryRepository    
    ) {}

    public function getUserRegistratedPages(array $user, $key): array
    {
        $collection = $this->accessRegistryRepository->findBy([
            'publicId' => $user['publicId']
        ]);

        if(empty($collection)){
            return [];
        }

        if ($key == 'application') {
            $result = array_filter($collection, function ($data) {
                return $data->getApplication();
            });
        }

        if ($key == 'domain') {
            $result = array_filter($collection, function ($data) {
                return $data->getDomain();
            });
        }

        return $result;
    }
}
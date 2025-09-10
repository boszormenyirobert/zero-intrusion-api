<?php

namespace App\Service\AccessRegistry\CredentialHubResolver;

final class ResolverService
{
    public function __construct(
        private CheckService $checkService,
        private DecryptService $decryptService,
        private FilterService $filterService,
        private WriteService $writeService,
        private DeleteService $deleteService
    ) {}

    public function getCheck(): CheckService { return $this->checkService; }

    public function getDecrypt(): DecryptService { return $this->decryptService; }

    public function getFilter(): FilterService { return $this->filterService; }

    public function getWrite(): WriteService { return $this->writeService; }
    
    public function getDelete(): DeleteService { return $this->deleteService; }
}
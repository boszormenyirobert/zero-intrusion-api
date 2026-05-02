<?php

declare(strict_types=1);

namespace App\Service\AccessRegistry\CredentialHubResolver;

final class ResolverService
{
    public function __construct(
        private readonly CheckService $checkService,
        private readonly DecryptService $decryptService,
        private readonly FilterService $filterService,
        private readonly WriteService $writeService,
        private readonly DeleteService $deleteService
    ) {}

    public function getCheck(): CheckService { return $this->checkService; }

    public function getDecrypt(): DecryptService { return $this->decryptService; }

    public function getFilter(): FilterService { return $this->filterService; }

    public function getWrite(): WriteService { return $this->writeService; }
    
    public function getDelete(): DeleteService { return $this->deleteService; }
}
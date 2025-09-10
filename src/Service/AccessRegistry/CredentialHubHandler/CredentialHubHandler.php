<?php

namespace App\Service\AccessRegistry\CredentialHubHandler;

final class CredentialHubHandler
{
    public function __construct(
        private RegistryRegistration $registryRegistration,
        private DeleteDomain $deleteDomain,
        private DeleteApplication $deleteApplication,
        private RegistryState $registryState
    ) {}

    public function getRegistration(): RegistryRegistration { return $this->registryRegistration; }
    public function getDeleteDomain(): DeleteDomain { return $this->deleteDomain; }
    public function getDeleteApplication(): DeleteApplication { return $this->deleteApplication; }
    public function getStateHandler(): RegistryState { return $this->registryState; }

}
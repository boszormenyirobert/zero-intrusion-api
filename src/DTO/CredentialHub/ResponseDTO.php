<?php
namespace App\DTO\CredentialHub;

use App\Entity\AuthBridge;

class ResponseDTO
{
    private bool $process = false;
    private bool|string $validation = false;
    private bool $process_check = false;
    private ?AuthBridge $data;
    private ?string $credential = null;
    private ?string $description = null;
    private ?string $userPublicId = null;

    public function __construct(
        bool $process,
        bool|string $validation,
        bool $process_check,
        ?AuthBridge $data = null
    ) {
        $this->process = $process;
        $this->validation = $validation;
        $this->process_check = $process_check;
        $this->data = $data;
    }

    public function isProcess(): bool
    {
        return $this->process;
    }

    public function setProcess(bool $process): void
    {
        $this->process = $process;
    }

    public function getValidation(): bool|string
    {
        return $this->validation;
    }

    public function setValidation(bool|string $validation): void
    {
        $this->validation = $validation;
    }

    public function isProcessCheck(): bool
    {
        return $this->process_check;
    }

    public function setProcessCheck(bool $process_check): void
    {
        $this->process_check = $process_check;
    }

    public function getData(): ?AuthBridge
    {
        return $this->data;
    }

    public function setData(?AuthBridge $data): void
    {
        $this->data = $data;
    }

    /**
     * Get the value of credential
     */ 
    public function getCredential()
    {
        return $this->credential;
    }

    /**
     * Set the value of credential
     *
     * @return  self
     */ 
    public function setCredential($credential)
    {
        $this->credential = $credential;

        return $this;
    }

    /**
     * Get the value of description
     */ 
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * Set the value of description
     *
     * @return  self
     */ 
    public function setDescription($description)
    {
        $this->description = $description;

        return $this;
    }

    public function setUserPublicId($userPublicId)
    {
        $this->userPublicId = $userPublicId;

        return $this;
    }

    public function getUserPublicId()
    {

        return $this->userPublicId;
    }    
        
    public function toDomainStateArray(): array
    {
        return [
            'process' => $this->isProcess(),
            'validation' => $this->getValidation(),
            'process_check' => $this->isProcessCheck(),
            'credential' => $this->getCredential(),
            'description' => $this->getDescription(),
            'publicId' => $this->getUserPublicId()
        ];
    }

    public function toStateArray(){
        return [
            'process' => $this->isProcess(),
            'validation' => $this->getValidation(),
            'process_check' => $this->isProcessCheck()
        ];        
    }

    public function toVaultStateArray(): array
    {
        return [
            'process' => $this->isProcess(),
            'validation' => $this->getValidation(),
            'process_check' => $this->isProcessCheck()
        ];
    }    
}

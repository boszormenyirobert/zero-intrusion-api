<?php

namespace App\Service\AuthBridge\DTO;

class ValidationDTO
{
    private bool $valid;
    private ?string $userSecret;
    private ?string $error;

    public function __construct(
        bool $valid,
        ?string $userSecret=null
    ) {
        $this->valid = $valid;
        $this->userSecret = $userSecret;
        if(!$valid){
            $this->error = 'Unvalid PrivateId';
        }
    }

    public function toArrayValid(){
        return [
            'valid' => $this->valid,
            'userSecret' => $this->userSecret            
        ];
    }

    public function toArrayUnValid(){
        return [
            'valid' => $this->valid,
            'error' => $this->error            
        ];
    }    

    /**
     * Get the value of valid
     */ 
    public function getValid():bool
    {
        return $this->valid;
    }

    /**
     * Get the value of userSecret
     */ 
    public function getUserSecret(): ?string
    {
        return $this->userSecret;
    }
}
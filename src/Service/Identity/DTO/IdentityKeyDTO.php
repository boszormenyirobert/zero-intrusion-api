<?php

namespace App\Service\Identity\DTO;


class IdentityKeyDTO
{
    private string $publicId;
    private string $privateId;
    private string $secret;
    private string $email = '--not-define-registration-process-one' ;
    private string $phone = '--not-define-registration-process-one';
    private string $fcmToken = '';

    public function __construct(
        string $publicId,
        string $privateId,
        string $secret
    ) {
        $this->publicId = $publicId;
        $this->privateId = $privateId;
        $this->secret = $secret;
    }

    public function toIdentityArray(): array
    {
        $secretData = [
            'publicId' => $this->publicId,
            'privateId' => $this->privateId,
            'secret' => $this->secret,
            'email' =>  $this->email,
            'phone' =>  $this->phone,
        ];

        return ['privateSecret' => $secretData];
    }

    public function toArray(): array
    {
       return [
            'publicId' => $this->publicId,
            'privateId' => $this->privateId,
            'secret' => $this->secret,
            'email' =>  $this->email,
            'phone' =>  $this->phone,
        ];
    }    

    /**
     * Get the value of privateId
     */ 
    public function getPrivateId()
    {
        return $this->privateId;
    }

    /**
     * Set the value of privateId
     *
     * @return  self
     */ 
    public function setPrivateId($privateId)
    {
        $this->privateId = $privateId;

        return $this;
    }
}
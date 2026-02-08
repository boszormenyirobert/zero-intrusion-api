<?php

namespace App\Service\Identity\DTO;


class IdentityKeyDTO
{
    private string $publicId;
    private string $privateId;
    private string $secret;
    private string $credentialSecret;
    private string $nfcEncryptionKey;
    private string $email = '--not-define-registration-process-one' ;
    private string $phone = '--not-define-registration-process-one';
    private string $fcmToken = '';

    public function __construct(
        string $publicId,
        string $privateId,
        string $secret,
        string $credentialSecret,
        string $nfcEncryptionKey
    ) {
        $this->publicId = $publicId;
        $this->privateId = $privateId;
        $this->secret = $secret;
        $this->credentialSecret = $credentialSecret;
        $this->nfcEncryptionKey = $nfcEncryptionKey;
    }

    public function toIdentityArray(): array
    {
        $secretData = [
            'publicId' => $this->publicId,
            'privateId' => $this->privateId,
            'secret' => $this->secret,
            'credentialSecret' => $this->credentialSecret,
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
            'credentialSecret' => $this->credentialSecret,
            'nfcEncryptionKey' => $this->nfcEncryptionKey,
            'email' =>  $this->email,
            'phone' =>  $this->phone,
        ];
    }    
    /**
     * Get the value of nfcEncryptionKey
     */
    public function getNfcEncryptionKey()
    {
        return $this->nfcEncryptionKey;
    }

    /**
     * Set the value of nfcEncryptionKey
     *
     * @return  self
     */
    public function setNfcEncryptionKey($nfcEncryptionKey)
    {
        $this->nfcEncryptionKey = $nfcEncryptionKey;
        return $this;
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
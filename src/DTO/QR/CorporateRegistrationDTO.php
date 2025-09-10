<?php
namespace App\DTO\QR;

use Symfony\Component\Validator\Constraints as Assert;
use App\DTO\QR\QrInterface;

class CorporateRegistrationDTO implements QrInterface
{
    #[Assert\NotBlank]
    public ?string $corporateId;

    #[Assert\NotBlank]
    public ?string $corporateAuthentication;

    #[Assert\NotBlank]
    public ?string $domain;

    #[Assert\NotBlank]
    public ?string $xExtensionAuthOne;

    #[Assert\NotBlank]
    public ?string $registrationProcessId;

    #[Assert\NotBlank]
    public ?string $type;

    #[Assert\NotBlank]
    public ?string $iv;

    #[Assert\NotBlank]
    public ?string $isNew;


    /**
     * Get the value of corporateId
     */ 
    public function getCorporateId()
    {
        return $this->corporateId;
    }

    /**
     * Set the value of corporateId
     *
     * @return  self
     */ 
    public function setCorporateId($corporateId)
    {
        $this->corporateId = $corporateId;

        return $this;
    }

    /**
     * Get the value of corporateAuthentication
     */ 
    public function getCorporateAuthentication()
    {
        return $this->corporateAuthentication;
    }

    /**
     * Set the value of corporateAuthentication
     *
     * @return  self
     */ 
    public function setCorporateAuthentication($corporateAuthentication)
    {
        $this->corporateAuthentication = $corporateAuthentication;

        return $this;
    }

    /**
     * Get the value of domain
     */ 
    public function getDomain()
    {
        return $this->domain;
    }

    /**
     * Set the value of domain
     *
     * @return  self
     */ 
    public function setDomain($domain)
    {
        $this->domain = $domain;

        return $this;
    }

    /**
     * Get the value of xExtensionAuthOne
     */ 
    public function getXExtensionAuthOne()
    {
        return $this->xExtensionAuthOne;
    }

    /**
     * Set the value of xExtensionAuthOne
     *
     * @return  self
     */ 
    public function setXExtensionAuthOne($xExtensionAuthOne)
    {
        $this->xExtensionAuthOne = $xExtensionAuthOne;

        return $this;
    }

    /**
     * Get the value of registrationProcessId
     */ 
    public function getRegistrationProcessId()
    {
        return $this->registrationProcessId;
    }

    /**
     * Set the value of registrationProcessId
     *
     * @return  self
     */ 
    public function setRegistrationProcessId($registrationProcessId)
    {
        $this->registrationProcessId = $registrationProcessId;

        return $this;
    }

    /**
     * Get the value of type
     */ 
    public function getType()
    {
        return $this->type;
    }

    /**
     * Set the value of type
     *
     * @return  self
     */ 
    public function setType($type)
    {
        $this->type = $type;

        return $this;
    }

    /**
     * Get the value of iv
     */ 
    public function getIv()
    {
        return $this->iv;
    }

    /**
     * Set the value of iv
     *
     * @return  self
     */ 
    public function setIv($iv)
    {
        $this->iv = $iv;

        return $this;
    }

    /**
     * Get the value of isNew
     */ 
    public function getIsNew()
    {
        return $this->isNew;
    }

    /**
     * Set the value of isNew
     *
     * @return  self
     */ 
    public function setIsNew($isNew)
    {
        $this->isNew = $isNew;

        return $this;
    }
}
<?php

declare(strict_types=1);

namespace App\Tests\DTO\Account;

use App\DTO\Account\AccountResponseDTO;
use App\Entity\BusinessServices;
use App\Entity\CorporateIdentity;
use PHPUnit\Framework\TestCase;

final class AccountResponseDTOTest extends TestCase
{
    public function testToArrayNormalizesEntitiesForJsonSerialization(): void
    {
        $corporateIdentity = (new CorporateIdentity())
            ->setDomain('example.com')
            ->setCallbackUserLogin('https://example.com/login')
            ->setCallbackUserRegistration('https://example.com/register')
            ->setCorporateIdKey('key-1')
            ->setCorporateIdSecret('secret-1')
            ->setIv(base64_encode(random_bytes(16)))
            ->setCorporateId('corp-1')
            ->setSslPrivateKey('private-key')
            ->setSslPublicKey('public-key');

        $businessServices = (new BusinessServices())
            ->setPasswordManager(true)
            ->setBiometric(false)
            ->setBasic(false)
            ->setPlus(false)
            ->setPro(false);

        $idProperty = new \ReflectionProperty(BusinessServices::class, 'id');
        $idProperty->setValue($businessServices, 9);

        $response = new AccountResponseDTO([$corporateIdentity], $businessServices);

        self::assertSame([
            'accounts' => [[
                'domain' => 'example.com',
                'callbackUserLogin' => 'https://example.com/login',
                'callbackUserRegistration' => 'https://example.com/register',
                'corporateIdKey' => 'key-1',
                'corporateIdSecret' => 'secret-1',
                'iv' => $corporateIdentity->getIv(),
                'corporateId' => 'corp-1',
                'sslPublicKey' => 'public-key',
            ]],
            'businessSubscription' => [
                'id' => 9,
                'pswManager' => true,
                'passwordManager' => true,
                'biometric' => false,
                'basic' => false,
                'plus' => false,
                'pro' => false,
            ],
        ], $response->toArray());
    }
}
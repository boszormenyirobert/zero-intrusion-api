<?php

declare(strict_types=1);

namespace App\Tests\Service\Corporate;

use App\Entity\CorporateIdentity;
use App\Service\Corporate\CorporateFollowUpDataApplier;
use PHPUnit\Framework\TestCase;

final class CorporateFollowUpDataApplierTest extends TestCase
{
    public function testApplyUpdatesCorporateIdentityFollowUpFields(): void
    {
        $corporate = new CorporateIdentity();
        $applier = new CorporateFollowUpDataApplier();

        $applier->apply($corporate, [
            'updateIdentity' => [
                'callbackUserLogin' => 'https://example.test/login',
                'callbackUserRegistration' => 'https://example.test/register',
                'domain' => 'example.test',
            ],
        ]);

        self::assertSame('https://example.test/login', $corporate->getCallbackUserLogin());
        self::assertSame('https://example.test/register', $corporate->getCallbackUserRegistration());
        self::assertSame('example.test', $corporate->getDomain());
    }
}

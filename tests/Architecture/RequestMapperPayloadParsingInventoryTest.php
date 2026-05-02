<?php

declare(strict_types=1);

namespace App\Tests\Architecture;

use App\Service\Account\AccountRequestMapper;
use App\Service\Business\BusinessCreateRequestMapper;
use App\Service\CredentialHub\Domain\Delete\DomainDeleteQrIdentityRequestMapper;
use App\Service\CredentialHub\Domain\Read\DomainReadQrIdentityRequestMapper;
use App\Service\CredentialHub\OneTouch\OneTouchQrIdentityRequestMapper;
use App\Service\CredentialHub\Shared\SharedRegistrationQrIdentityRequestMapper;
use App\Service\CredentialHub\Vault\Edit\VaultEditQrIdentityRequestMapper;
use App\Service\CredentialHub\Vault\Read\VaultReadQrIdentityRequestMapper;
use App\Service\Corporate\CorporateIdentityInitializeRequestMapper;
use App\Service\Device\Identity\RecoverySettingsRequestMapper;
use App\Service\Device\Nfc\NfcDecryptRequestMapper;
use App\Service\Device\Restore\ReplaceDevicePinRequestMapper;
use App\Service\Device\Restore\ReplaceDeviceRequestMapper;
use App\Service\Payload\JsonPayloadDecoder;
use App\Service\User\Login\LoginQrIdentityRequestMapper;
use App\Service\User\Registration\RegistrationQrIdentityRequestMapper;
use App\Service\User\SecureDevice\SecureDeviceQrIdentityRequestMapper;
use PHPUnit\Framework\TestCase;

final class RequestMapperPayloadParsingInventoryTest extends TestCase
{
    /** @var list<class-string> */
    private const MAPPER_CLASSES = [
        AccountRequestMapper::class,
        BusinessCreateRequestMapper::class,
        CorporateIdentityInitializeRequestMapper::class,
        RegistrationQrIdentityRequestMapper::class,
        LoginQrIdentityRequestMapper::class,
        SecureDeviceQrIdentityRequestMapper::class,
        ReplaceDeviceRequestMapper::class,
        ReplaceDevicePinRequestMapper::class,
        RecoverySettingsRequestMapper::class,
        NfcDecryptRequestMapper::class,
        SharedRegistrationQrIdentityRequestMapper::class,
        OneTouchQrIdentityRequestMapper::class,
        VaultReadQrIdentityRequestMapper::class,
        VaultEditQrIdentityRequestMapper::class,
        DomainReadQrIdentityRequestMapper::class,
        DomainDeleteQrIdentityRequestMapper::class,
    ];

    public function testRequestMappersUseSharedJsonPayloadDecoder(): void
    {
        foreach (self::MAPPER_CLASSES as $className) {
            $source = $this->classSource($className);

            self::assertStringContainsString(JsonPayloadDecoder::class, $source, $className . ' should depend on the shared JSON payload decoder.');
            self::assertStringNotContainsString('private function decodePayload', $source, $className . ' should not duplicate local payload decoding helpers.');
            self::assertStringNotContainsString('json_decode(', $source, $className . ' should not parse JSON inline.');
        }
    }

    private function classSource(string $className): string
    {
        $reflection = new \ReflectionClass($className);
        $fileName = $reflection->getFileName();

        self::assertIsString($fileName);

        $contents = file_get_contents($fileName);
        self::assertIsString($contents);

        return $contents;
    }
}
<?php

declare(strict_types=1);

namespace App\Tests\Config;

use App\Controller\Account\AccountController;
use App\Controller\CredentialHub\Domain\Delete\DomainDeleteController;
use App\Controller\CredentialHub\Domain\Read\DomainReadController;
use App\Controller\CredentialHub\OneTouch\OneTouchController;
use App\Controller\CredentialHub\Shared\SharedRegistrationController;
use App\Controller\CredentialHub\Vault\Delete\VaultDeleteController;
use App\Controller\CredentialHub\Vault\Edit\VaultEditController;
use App\Controller\CredentialHub\Vault\Read\VaultReadController;
use App\Controller\DeviceManagement\Nfc\NfcController;
use PHPUnit\Framework\TestCase;

final class ResponseContractInventoryTest extends TestCase
{
    public function testCriticalApiEndpointsKeepTheirCurrentSuccessResponseContracts(): void
    {
        foreach ($this->envelopedEndpoints() as [$className, $methodName]) {
            $source = $this->methodSource($className, $methodName);

            self::assertStringContainsString('createSuccessResponse(', $source, sprintf('%s::%s must keep the enveloped success contract.', $className, $methodName));
        }

        foreach ($this->rawJsonEndpoints() as [$className, $methodName]) {
            $source = $this->methodSource($className, $methodName);

            self::assertStringContainsString('new JsonResponse(', $source, sprintf('%s::%s must keep the raw JsonResponse contract.', $className, $methodName));
            self::assertStringNotContainsString('createSuccessResponse(', $source, sprintf('%s::%s must not silently switch to the enveloped success contract.', $className, $methodName));
        }
    }

    public function testCriticalApiEndpointsKeepCentralizedErrorHandling(): void
    {
        foreach (array_merge($this->envelopedEndpoints(), $this->rawJsonEndpoints()) as [$className, $methodName]) {
            $source = $this->methodSource($className, $methodName);

            self::assertTrue(
                str_contains($source, 'handleException(') || str_contains($source, 'createErrorResponse('),
                sprintf('%s::%s must keep centralized error handling.', $className, $methodName)
            );
        }
    }

    /** @return list<array{class-string, string}> */
    private function envelopedEndpoints(): array
    {
        return [
            [SharedRegistrationController::class, 'sharedRegistrationQrIdentity'],
            [SharedRegistrationController::class, 'sharedRegistrationState'],
            [DomainReadController::class, 'domainReadQrIdentity'],
            [DomainReadController::class, 'domainReadCredentialDecrypted'],
            [DomainReadController::class, 'domainReadCredential'],
            [DomainReadController::class, 'domainReadState'],
            [DomainDeleteController::class, 'domainDeleteQrIdentity'],
            [DomainDeleteController::class, 'domainDeleteState'],
            [VaultReadController::class, 'vaultReadQrIdentity'],
            [VaultReadController::class, 'vaultReadCredentialDecrypted'],
            [VaultReadController::class, 'vaultReadState'],
            [VaultEditController::class, 'vaultEditQrIdentity'],
            [VaultEditController::class, 'vaultEditState'],
            [VaultDeleteController::class, 'vaultDeleteQrIdentity'],
            [VaultDeleteController::class, 'vaultDeleteState'],
            [OneTouchController::class, 'oneTouchQrIdentity'],
            [OneTouchController::class, 'oneTouchState'],
        ];
    }

    /** @return list<array{class-string, string}> */
    private function rawJsonEndpoints(): array
    {
        return [
            [SharedRegistrationController::class, 'sharedRegistrationNewToEncrypt'],
            [SharedRegistrationController::class, 'sharedRegistrationNew'],
            [VaultReadController::class, 'vaultReadCredential'],
            [VaultEditController::class, 'vaultEditCredential'],
            [VaultDeleteController::class, 'vaultDeleteCredential'],
            [OneTouchController::class, 'oneTouchIdentifier'],
            [AccountController::class, 'account'],
            [NfcController::class, 'getNfcUsers'],
            [NfcController::class, 'decryptNfcCardData'],
        ];
    }

    private function methodSource(string $className, string $methodName): string
    {
        $reflectionMethod = new \ReflectionMethod($className, $methodName);
        $fileName = $reflectionMethod->getFileName();

        self::assertIsString($fileName);

        $lines = file($fileName, FILE_IGNORE_NEW_LINES);
        self::assertIsArray($lines);

        $start = $reflectionMethod->getStartLine() - 1;
        $length = $reflectionMethod->getEndLine() - $reflectionMethod->getStartLine() + 1;

        return implode("\n", array_slice($lines, $start, $length));
    }
}
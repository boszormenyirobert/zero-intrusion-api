<?php

declare(strict_types=1);

namespace App\Service\Payload;

final class PayloadIntegrityKeyRegistry
{
    /** @var array<string, string> */
    private const ALLOWED_INTEGRITY_KEYS = [
        'one_touch_qr_identity' => 'one_touch_qr_identity',
        'one_touch_identifier' => 'one_touch_identifier',
        'one_touch_state' => 'one_touch_state',
        'domain_read_qr_identity' => 'domain_read_qr_identity',
        'domain_read_credential' => 'domain_read_credential',
        'domain_read_credential_encrypted' => 'domain_read_credential_encrypted',
        'domain_read_state' => 'domain_read_state',
        'domain_delete_qr_identity' => 'domain_delete_qr_identity',
        'domain_delete_credential' => 'domain_delete_credential',
        'domain_delete_state' => 'domain_delete_state',
        'shared_registration_qr_identity' => 'shared_registration_qr_identity',
        'shared_registration_new_to_encrypt' => 'shared_registration_new_to_encrypt',
        'shared_registration_new' => 'shared_registration_new',
        'shared_registration_state' => 'shared_registration_state',
        'vault_read_qr_identity' => 'vault_read_qr_identity',
        'vault_read_credential' => 'vault_read_credential',
        'vault_read_credential_encrypted' => 'vault_read_credential_encrypted',
        'vault_read_state' => 'vault_read_state',
        'vault_edit_qr_identity' => 'vault_edit_qr_identity',
        'vault_edit_credential' => 'vault_edit_credential',
        'vault_edit_state' => 'vault_edit_state',
        'vault_delete_qr_identity' => 'vault_delete_qr_identity',
        'vault_delete_credential' => 'vault_delete_credential',
        'vault_delete_state' => 'vault_delete_state',
        'getIdentity' => 'getIdentity',
        'business_create' => 'business_create',
        'updateIdentity' => 'updateIdentity',
        'firstSecret' => 'firstSecret',
        'recoverySettings' => 'recoverySettings',
        'replaceDevice' => 'replaceDevice',
        'restorePin' => 'restorePin',
        'browserRegistrationVaultIdentity' => 'browserRegistrationVaultIdentity',
    ];

    public function isAllowed(string $key): bool
    {
        return array_key_exists($key, self::ALLOWED_INTEGRITY_KEYS);
    }
}
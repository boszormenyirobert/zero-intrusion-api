<?php

namespace App\Controller\CredentialHub;

final class PayloadKeys
{
    public const DOMAIN_DELETE_QR_IDENTITY = 'domain_delete_qr_identity';
    public const DOMAIN_DELETE_CREDENTIAL = 'domain_delete_credential';
    public const DOMAIN_DELETE_STATE = 'domain_delete_state';
    public const REMOVE_PROCESS_ID = 'removeProcessId';

    public const DOMAIN_READ_QR_IDENTITY = 'domain_read_qr_identity';
    public const DOMAIN_PROCESS_ID = 'domainProcessId';
    public const DOMAIN_READ_CREDENTIAL = 'domain_read_credential';
    public const DOMAIN_READ_CREDENTIAL_DECRYPTED = 'domain_read_credential_decrypted';
    public const DOMAIN_READ_STATE = 'domain_read_state';

    public const VAULT_READ_QR_IDENTITY = 'vault_read_qr_identity';
    public const VAULT_PROCESS_ID = 'applicationProcessId';
    public const VAULT_READ_CREDENTIAL = 'vault_read_credential';
    public const VAULT_READ_STATE = 'vault_read_state';

    public const VAULT_DELETE_QR_IDENTITY = 'vault_delete_qr_identity';
    public const VAULT_DELETE_CREDENTIAL = 'vault_delete_credential';
    public const VAULT_DELETE_STATE = 'vault_delete_state';
    public const VAULT_DELETE_PROCESS_ID = 'removeProcessId';    

    public const VAULT_EDIT_QR_IDENTITY = 'vault_edit_qr_identity';
    public const VAULT_EDIT_CREDENTIAL = 'vault_edit_credential';
    public const VAULT_EDIT_STATE = 'vault_edit_state';
    public const VAULT_EDIT_PROCESS_ID = 'registrationProcessId';      
   

}
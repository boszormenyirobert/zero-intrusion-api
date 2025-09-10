<?php

namespace App\Controller\PayloadValidator;

use Symfony\Component\HttpFoundation\Request;
use Psr\Log\LoggerInterface;
use App\Service\Shared\RequestService;
use App\Exception\MissingKeyException;

class PayloadValidator
{
    const ALLOWED_INTEGRITY_KEYS = [
        'domain_read_qr_identity' =>'domain_read_qr_identity',
        'domain_read_credential' => 'domain_read_credential',        
        'domain_read_state' => 'domain_read_state',

        'domain_delete_qr_identity' => 'domain_delete_qr_identity',
        'domain_delete_credential' => 'domain_delete_credential',
        'domain_delete_state' => 'domain_delete_state',

        'shared_registration_qr_identity' => 'shared_registration_qr_identity',
        'shared_registration_new' => 'shared_registration_new',
        'shared_registration_state' => 'shared_registration_state',

        'vault_read_qr_identity' => 'vault_read_qr_identity',
        'vault_read_credential' => 'vault_read_credential',
        'vault_read_state' => 'vault_read_state',

        'vault_edit_qr_identity' => 'vault_edit_qr_identity',
        'vault_edit_credential' => 'vault_edit_credential',
        'vault_edit_state' => 'vault_edit_state',    

        'vault_delete_qr_identity' => 'vault_delete_qr_identity',
        'vault_delete_credential' => 'vault_delete_credential',
        'vault_delete_state' => 'vault_delete_state',        

        // TODO: Missing refactoring
        'getIdentity' => 'getIdentity',
        'business_create' => 'business_create',
        'updateIdentity' => 'updateIdentity',
        'firstSecret' => 'firstSecret',
        'recoverySettings' => 'recoverySettings',
        'replaceDevice' => 'replaceDevice',
        'restorePin' => 'restorePin',
        'browserRegistrationVaultIdentity' => 'browserRegistrationVaultIdentity'
    ];

    public function __construct(
        private LoggerInterface $logger,
        private RequestService $requestService
    ) {}

    /**
     * Validate the payload by checking for required keys and their presence.
     * 
     * @param Request $request
     * @param string|null $key
     * @return array
     * @throws MissingKeyException
     */
    public function validatePayload(Request $request, ?string $key = null): array
    {
        $payload = $this->getValidatedPayload($request);

        if ($key && !isset(self::ALLOWED_INTEGRITY_KEYS[$key])) {
            $this->logger->critical($key . ' is now whitelisted');
            throw new MissingKeyException(sprintf('Not authorized integrity key: ', $key));
        }

        if ($key && !isset($payload[$key])) {
            $this->logger->critical($key . ' is missing in the payload');
            throw new MissingKeyException(sprintf('Property "%s" missing: ', $key));
        }

        return $payload;
    }

    public function getValidatedPayload(Request $request, ?string $key = null): array
    {
        try {
            $payload = $request->attributes->get('json_payload');
            $validatedPayload = $this->requestService->validPayload($payload);
            if ($key && !isset($validatedPayload[$key])) {
                $this->logger->critical(sprintf('Property "%s" missing', $key));
                throw new MissingKeyException(sprintf('Property "%s" missing', $key));
            }

            return $validatedPayload;
        } catch (\Exception $e) {
            $this->logger->error('Payload validation failed', [
                'error' => $e->getMessage(),
                'payload' => $request->attributes->get('json_payload')
            ]);
            throw $e;
        }
    }

}

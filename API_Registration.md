# TODO: AUTH CONTROLL ON THE EASYPUBLIC SIDE READY, BUT THE REQUEST IS NOT SAFE ON THE EASYLOGIN SIDE

# API Endpoint Documentation: `/api/registration/corporate/identity`

## Overview

This document describes the behavior and structure of the `GET /api/registration/corporate/identity` endpoint, along with related components involved in the corporate registration and identity delivery process.

---

## Endpoint Summary

* **Method:** `GET`
* **Path:** `/api/registration/corporate/identity`
* **Purpose:** Returns an encrypted, pre-stored corporate identity payload.
* **Authentication:** HMAC-based Authorization header.

---

## Response

### Headers

* `Content-Type: application/json`
* `Authorization: HMAC <clientApiKey>:<signature>`

### Body Example

```json
{
  "corporateIdentity": "<base64_encrypted_payload>",
  "iv": "<base64_iv>"
}
```

* `corporateIdentity`: AES-256-CBC encrypted corporate identity payload.
* `iv`: Initialization vector used for encryption.

---

## Workflow Breakdown

1. The `CorporateRegistrationService::deliverConfidentalRegistrationData()` method retrieves the current pre-generated identity.
2. The `IdentityService::getIdentity()` method ensures the identity has been initialized, otherwise throws an error.
3. The identity is encrypted using the `CrypterService` with a symmetric key (`DATA_HASH_SECRET`) and IV.
4. An HMAC `Authorization` header is generated using the `AuthorizationHelper`.
5. The encrypted payload and header are packaged into a JSON response.

---

## Encryption Logic

* Cipher: `AES-256-CBC`
* IV: 16-byte random value
* Encryption and Decryption handled in `CrypterService`
* Key retrieved from application parameter: `DATA_HASH_SECRET`

---

## HMAC Authentication

* Format: `HMAC <clientApiKey>:<signature>`
* Signature: `HMAC_SHA256(encryptedData|iv, serviceSecret)`
* Used to authenticate the source and integrity of the data.

---

## Code Involved

### Controller

**Method:** `CorporateRegistrationController::serviceIdentity()`

```php
#[Route('/identity', name: 'service_identity_written_in_db', methods: ['GET'])]
public function serviceIdentity(): Response
{
    $identityConfig = $this->corporateRegistrationService->deliverConfidentalRegistrationData();
    return new Response($identityConfig['body'], 200, $identityConfig['headers']);
}
```

### Service: `CorporateRegistrationService`

```php
public function deliverConfidentalRegistrationData(): array
{
    $identity = $this->identityService->getIdentity();
    $encryptedData = new CrypterService($identity, $this->params);

    $authHelper = new AuthorizationHelper(
        $this->params->get('SERVICE_API_KEY'),
        $this->params->get('SERVICE_API_SECRET')
    );

    return $authHelper->buildResponse(
        $authHelper->getAuthHeader($encryptedData),
        $encryptedData,
        $authHelper->getIvBase64()
    );
}
```

### Service: `IdentityService`

```php
public function initializeIdentity(): void
{
    $this->newIdentity = UtilityHelper::generateIdentity();
    $encryptedIdentity = $this->crypterDatabaseService->encyptDataObject(
        $this->newIdentity,
        $this->params
    );
    $this->corporateRegistrationDatabaseService->addNewIdentity($encryptedIdentity);
}

public function getIdentity(): array
{
    if (empty($this->newIdentity)) {
        throw new \LogicException('Identity not initialized.');
    }
    return $this->newIdentity;
}
```

### Crypter: `CrypterService`

```php
public function encryptData(): string
{
    $plaintext = json_encode($this->data);
    $encrypted = openssl_encrypt($plaintext, self::CIPHER, $this->key, 0, $this->iv);
    return base64_encode($this->iv . $encrypted);
}
```

---

## Security Considerations

* All data is encrypted at rest and in transit.
* Authorization is validated using a shared secret and HMAC.
* The IV and encrypted payload are required to decrypt and reconstruct the original identity data.

---

## Related Endpoints

* `GET /api/registration/corporate/identity/{id}`: For testing, decrypts and returns a stored identity.
* `POST /api/registration/corporate/new`: Accepts encrypted identity payload for registration.

---

## Notes

* The `AuthorizationHelper` generates signatures per request.
* The encrypted identity payload can only be decrypted using the shared key and IV.
* The system supports internal testing of encrypted data via the test endpoint.

# Zero Intrusion API ↔ HUB interface contract

## Purpose

This document describes the runtime integration contract between `zero-intrusion-hub` and `zero-intrusion-api`.

It is intended as a compatibility guide during refactoring. The goal is to preserve the existing wire contract even when internal API or HUB code changes.

## Scope

This contract covers:

- request envelope sent from HUB to API
- response envelope sent from API to HUB
- HMAC and payload encryption expectations
- process identifier semantics
- endpoint validation expectations
- compatibility rules for future refactors

It does **not** define internal persistence schemas or internal service implementation details.

---

## 1. Transport contract

### 1.1 Protocol

- Transport: HTTP(S)
- Content type: `application/json`
- Primary authorization header: `X-Auth`
- Primary encrypted payload field: `zeroIntrusionProyApi`
- Initialization vector field: `iv`

### 1.2 Request envelope sent by HUB

For HMAC-protected API endpoints, HUB sends a JSON object with this outer structure:

```json
{
  "zeroIntrusionProyApi": "<encrypted-base64-payload>",
  "iv": "<base64-iv>"
}
```

HUB also sends:

```text
X-Auth: HMAC <service_api_key>:<signature>:<timestamp>
```

### 1.3 API-side request validation expectations

API validates:

1. request body is valid JSON
2. `zeroIntrusionProyApi` exists when HMAC is required
3. `iv` exists when HMAC is required
4. `X-Auth` exists and is correctly formatted
5. HMAC signature matches the configured shared secret
6. decrypted `zeroIntrusionProyApi` payload contains the endpoint-specific business keys

Relevant API enforcement points currently include:

- [zero-intrusion-api/src/EventListener/JsonValidationListener.php](zero-intrusion-api/src/EventListener/JsonValidationListener.php)
- [zero-intrusion-api/src/EventListener/HmacAnnotationListener.php](zero-intrusion-api/src/EventListener/HmacAnnotationListener.php)
- [zero-intrusion-api/src/EventListener/HmacMobileValidationListener.php](zero-intrusion-api/src/EventListener/HmacMobileValidationListener.php)
- [zero-intrusion-api/src/EventListener/HmacExtensionValidationListener.php](zero-intrusion-api/src/EventListener/HmacExtensionValidationListener.php)
- [zero-intrusion-api/src/EventListener/HmacDesktopValidationListener.php](zero-intrusion-api/src/EventListener/HmacDesktopValidationListener.php)

---

## 2. Shared secrets and cryptographic expectations

### 2.1 Shared configuration

The integration depends on matching values on both sides for:

- `SERVICE_API_KEY`
- `SERVICE_API_SECRET`
- `DATA_HASH_SECRET`

These are referenced on API side in:

- [zero-intrusion-api/src/Helper/UtilityHelper.php](zero-intrusion-api/src/Helper/UtilityHelper.php)
- [zero-intrusion-api/src/Helper/AuthorizationHelper.php](zero-intrusion-api/src/Helper/AuthorizationHelper.php)
- [zero-intrusion-api/src/Service/Crypters/CrypterService.php](zero-intrusion-api/src/Service/Crypters/CrypterService.php)

And on HUB side in:

- [zero-intrusion-hub/src/Helper/AuthorizationHelper.php](zero-intrusion-hub/src/Helper/AuthorizationHelper.php)
- [zero-intrusion-hub/src/Service/Instance/InstanceSettingsService.php](zero-intrusion-hub/src/Service/Instance/InstanceSettingsService.php)

### 2.2 Request signing contract

The request signature is calculated over:

$$
message = zeroIntrusionProyApi \; || \; "|" \; || \; iv
$$

and then:

$$
signature = HMAC\_SHA256(message, SERVICE\_API\_SECRET)
$$

The API key is included in `X-Auth` together with the signature and timestamp.

### 2.3 Payload encryption contract

The business payload stored in `zeroIntrusionProyApi` is encrypted symmetrically using the shared `DATA_HASH_SECRET`.

The current API implementation uses AES-256-CBC in:

- [zero-intrusion-api/src/Service/Crypters/CrypterService.php](zero-intrusion-api/src/Service/Crypters/CrypterService.php)

Compatibility rule:

- encryption algorithm, payload field name, and decryption expectations must remain backward-compatible unless both repositories are changed together deliberately

---

## 3. API response contract consumed by HUB

### 3.1 Default response shape

For QR/init style endpoints, API responds with a structure containing:

- headers
- body

The effective response body returned through controllers is typically JSON that contains:

```json
{
  "corporateIdentity": "<encrypted-base64-payload>",
  "iv": "<base64-iv>"
}
```

and an `X-Auth` response header.

The API response builder currently lives in:

- [zero-intrusion-api/src/Helper/AuthorizationHelper.php](zero-intrusion-api/src/Helper/AuthorizationHelper.php)

### 3.2 HUB parsing expectations

HUB expects the API QR/init response payload to expose process identifiers and QR data after decryption/parsing.

Current evidence of HUB expectations:

- [zero-intrusion-hub/src/DTO/QrCodeResponseDTO.php](zero-intrusion-hub/src/DTO/QrCodeResponseDTO.php)
- [zero-intrusion-hub/src/Service/User/Login/Api/LoginApiRequestMapper.php](zero-intrusion-hub/src/Service/User/Login/Api/LoginApiRequestMapper.php)
- [zero-intrusion-hub/src/DTO/RegistrationProcessDTO.php](zero-intrusion-hub/src/DTO/RegistrationProcessDTO.php)

Important consumed fields include:

- `domainProcessId`
- `registrationProcessId`
- `qrCode`

Compatibility rule:

- these field names must not change
- their value types must remain string-or-null compatible with existing HUB DTO parsing

---

## 4. Business payload contract inside `zeroIntrusionProyApi`

After decryption, HUB and API exchange endpoint-specific payloads.

### 4.1 Common pattern

The decrypted payload is an associative object keyed by the endpoint business operation, for example:

```json
{
  "business_create": {
    "publicId": "..."
  }
}
```

or endpoint-specific payloads used by mobile, extension, desktop, or HUB controllers.

### 4.2 Integrity-key / payload-key expectations

The API currently maintains a whitelist of expected business keys in:

- [zero-intrusion-api/src/Controller/PayloadValidator/PayloadValidator.php](zero-intrusion-api/src/Controller/PayloadValidator/PayloadValidator.php)

Examples include:

- `business_create`
- `getIdentity`
- `updateIdentity`
- `firstSecret`
- `recoverySettings`
- `replaceDevice`
- `restorePin`
- `browserRegistrationVaultIdentity`
- `one_touch_qr_identity`
- `one_touch_identifier`
- `domain_read_qr_identity`
- `shared_registration_qr_identity`
- `vault_read_qr_identity`
- `vault_edit_qr_identity`
- `vault_delete_qr_identity`

Compatibility rule:

- existing key names are part of the wire contract
- renaming a payload key requires coordinated HUB + API change

---

## 5. Process identifier contract

Process identifiers are central to the integration and must remain stable.

### 5.1 Known process identifiers

The current shared process-id vocabulary includes:

- `registrationProcessId`
- `domainProcessId`
- `removeProcessId`
- `applicationProcessId`
- `oneTouchProcessId`

### 5.2 Meaning

- `registrationProcessId`: write/register/edit style flows
- `domainProcessId`: login/domain-read style flows
- `removeProcessId`: delete/removal flows
- `applicationProcessId`: application credential flows
- `oneTouchProcessId`: one-touch flows

### 5.3 Compatibility requirement

These names are used in both repositories and must remain unchanged unless both sides are updated together.

Examples on API side:

- [zero-intrusion-api/src/Controller/User/UserService.php](zero-intrusion-api/src/Controller/User/UserService.php)
- [zero-intrusion-api/src/Service/AuthBridge/AuthBridgeService.php](zero-intrusion-api/src/Service/AuthBridge/AuthBridgeService.php)
- [zero-intrusion-api/src/EventListener/HmacMobileValidationListener.php](zero-intrusion-api/src/EventListener/HmacMobileValidationListener.php)
- [zero-intrusion-api/src/EventListener/HmacExtensionValidationListener.php](zero-intrusion-api/src/EventListener/HmacExtensionValidationListener.php)

Examples on HUB side:

- [zero-intrusion-hub/src/DTO/QrCodeResponseDTO.php](zero-intrusion-hub/src/DTO/QrCodeResponseDTO.php)
- [zero-intrusion-hub/src/Service/User/Login/Api/LoginApiRequestMapper.php](zero-intrusion-hub/src/Service/User/Login/Api/LoginApiRequestMapper.php)
- [zero-intrusion-hub/src/Service/User/Registration/HUB/RegistrationService.php](zero-intrusion-hub/src/Service/User/Registration/HUB/RegistrationService.php)

---

## 6. QR/init contract

### 6.1 Login flow

API login QR generation currently builds a payload that contains at least:

- `domain`
- `domainProcessId`
- `xExtensionAuthOne`
- `type`
- `corporateId`
- `corporateAuthentication`
- `source`

Relevant API source:

- [zero-intrusion-api/src/DTO/QR/UserLoginDTO.php](zero-intrusion-api/src/DTO/QR/UserLoginDTO.php)
- [zero-intrusion-api/src/Controller/User/UserService.php](zero-intrusion-api/src/Controller/User/UserService.php)

Relevant HUB consumer:

- [zero-intrusion-hub/src/Controller/User/Login/Api/LoginController.php](zero-intrusion-hub/src/Controller/User/Login/Api/LoginController.php)
- [zero-intrusion-hub/src/Service/User/Login/Api/LoginApiRequestMapper.php](zero-intrusion-hub/src/Service/User/Login/Api/LoginApiRequestMapper.php)

Minimum compatibility guarantee:

- `domainProcessId` must remain present for login QR responses
- QR data must remain parseable into the current HUB login DTO pipeline

### 6.2 Registration flow

API registration QR generation currently builds payloads containing at least:

- `corporateId`
- `corporateAuthentication`
- `domain`
- `xExtensionAuthOne`
- `registrationProcessId`
- `type`
- `isNew`

Relevant API source:

- [zero-intrusion-api/src/DTO/QR/CorporateRegistrationDTO.php](zero-intrusion-api/src/DTO/QR/CorporateRegistrationDTO.php)
- [zero-intrusion-api/src/Controller/User/UserService.php](zero-intrusion-api/src/Controller/User/UserService.php)

Relevant HUB consumer:

- [zero-intrusion-hub/src/DTO/RegistrationProcessDTO.php](zero-intrusion-hub/src/DTO/RegistrationProcessDTO.php)
- [zero-intrusion-hub/src/DTO/RegistrationViewDataDTO.php](zero-intrusion-hub/src/DTO/RegistrationViewDataDTO.php)

Minimum compatibility guarantee:

- `registrationProcessId` must remain present for registration QR responses
- `qrCode` must remain present in the decrypted QR/init response payload

---

## 7. Response semantics beyond QR/init endpoints

For non-QR endpoints, API controllers frequently normalize response payloads to a JSON object with fields such as:

- `success`
- `error`
- `process`
- `validation`
- `process_check`

Relevant API helper:

- [zero-intrusion-api/src/Helper/ResponseHelper.php](zero-intrusion-api/src/Helper/ResponseHelper.php)

Compatibility rule:

- response booleans and top-level error structure should remain stable where HUB or clients already rely on them
- entity-like objects returned from API must be normalized into arrays/scalars, not leaked as raw Doctrine objects

---

## 8. Security and validation attributes

API uses attribute-driven endpoint validation. The contract between HUB and API assumes that HUB calls endpoints in a way compatible with these constraints.

Relevant attributes include:

- `RequireHmac`
- `RequireJson`
- `MobileHmac`
- `ExtensionHmac`
- `DesktopHmac`

Compatibility rule:

- if an endpoint is protected by one of these attributes, HUB must continue sending the required envelope, header, and business payload shape expected by that validator path

---

## 9. Backward-compatibility rules for refactoring

During refactors, the following must be treated as external contract and therefore preserved:

1. header name `X-Auth`
2. envelope fields `zeroIntrusionProyApi` and `iv`
3. shared-secret semantics for `SERVICE_API_KEY`, `SERVICE_API_SECRET`, `DATA_HASH_SECRET`
4. process-id field names
5. endpoint payload-key names in `PayloadValidator`
6. top-level QR/init response body shape consumed by HUB
7. scalar/array JSON serialization stability for frontend- and HUB-consumed responses

Safe to change internally:

- controller/service decomposition
- DTO/request-mapper structure inside API
- internal helper extraction
- logger implementation details
- test structure

Unsafe to change without coordinated HUB update:

- wire field names
- HMAC message format
- encrypted envelope shape
- required process-id names
- required payload-key names

---

## 10. Recommended verification after any API refactor

After changing API code that may affect HUB integration, verify at minimum:

1. full API PHPUnit suite passes
2. QR login response still contains `domainProcessId`
3. QR registration response still contains `registrationProcessId`
4. encrypted request envelope from HUB still validates in API
5. API response still returns parseable JSON and expected top-level fields
6. no raw entity serialization leaks into HUB/frontend responses

---

## 11. Current status

As of the current refactor effort, this file documents the **preserved contract**, not an idealized future protocol.

If API and HUB are redesigned later, this document should either:

- be updated as the new source of truth, or
- be replaced by versioned contract documentation such as `v1`, `v2`, etc.

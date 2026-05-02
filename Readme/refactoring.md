# zero-intrusion-api – teljes refaktor értékelés és prioritási terv

## Working rules

Refactoralhatod a szabalyokk szerint:
- TDD szerint kell haladni: `red -> green -> refactor`
- A publikus API input/output shape nem változhat
- Kis lépésekben kell refaktorálni
- Minden kis lépés után célzott teszteket kell futtatni
- Minden lezárt refaktor lépés után teljes regressziós tesztfuttatás kell
- A rendszernek minden pillanatban futtathatónak kell maradnia
- Update the file refactoring.md es torold a feladatbol ami kesz van

- Utana automatikusan vedd a kovetkezo feladatot a szabalyok szerint,
ha nem tudsz tovabb menni, akkor allj meg
- ne irj history-t, mindig a tesztek eredmenyeket ird csak ki, es mi a kovetkezo feladat/file

**Ajánlott stratégia:**
- mindig csak egy service-et érintsen egy refaktor lépés
- először belső typed result vagy exception
- utána adapter a jelenlegi output shape megtartására

### 1. Hibakezelés és visszatérési formák egységesítése

**Mi a gond:**
- több service még mindig `array` hibapayloadot ad vissza
- több helyen union return type van (`Entity|array`, `array|string|false`)
- ugyanazon domainen belül sincs egységes szabály

**Mi a cél:**
- belső rétegben exception vagy typed result DTO
- a publikus controller-response maradhat a jelenlegi JSON shape
- a service-ek publikus szerződése legyen szűkebb és olvashatóbb



**Érintett példák:**
- jelenleg nincs nyitott, külön item 1-re bontott példa


### 2. Credential Hub result DTO-k következetes bevezetése

**Mi a gond:**
- több controller raw `JsonResponse` payloadot kap olyan service-től, amely domain logikát és transport shape-et egyszerre kezel

**Mi a cél:**
- a service DTO-t vagy exception-t adjon vissza
- a controller fordítsa le a jelenlegi JSON szerződésre

**Érintett példák:**
- jelenleg nincs nyitott, külön item 2-re bontott példa

### 3. Payload / HMAC / envelope felelősségek további tisztítása

**Mi a gond:**
- a titkosított envelope és a HMAC-validáció több rétegben is megjelenik
- a `zeroIntrusionProyApi` mező körüli logika szétszórt

**Mi a cél:**
- egyértelmű réteghatárok
- kevesebb duplikáció
- könnyebb tesztelhetőség

**Érintett példák:**
- jelenleg nincs nyitott, külön item 3-ra bontott példa

### 4. `CrypterService` és rokon szolgáltatások szűkítése

**Mi a gond:**
- túl sok union típus és belső állapot
- `array|string|null` és `array|string` visszatérések nehezítik a hibamentes használatot

**Mi a cél:**
- kisebb, célzott crypter szolgáltatások
- kevesebb állapot
- explicit input/output

**Érintett példák:**
- jelenleg nincs nyitott, külön item 4-re bontott példa

### 5. `ResponseHelper` implicit átalakításainak csökkentése

**Mi a gond:**
- a helper többféle objektumot külön metódus alapján fordít tömbbé
- ez elrejti a tényleges response contractot

**Mi a cél:**
- explicit response DTO-k
- explicit `toArray()` vagy célzott mapper

**Érintett példák:**
- [src/Helper/ResponseHelper.php](../src/Helper/ResponseHelper.php)

## P2 – tisztasági és olvashatósági cleanup

### 6. Naming cleanup

**Mi a gond:**
- több rosszul elnevezett osztály, mező, entity és string kulcs van a rendszerben

**Kiemelt hibás nevek:**
- `zeroIntrusionProyApi`
- `Healty`
- `registrated`
- `requestControll`

**Érintett példák:**
- [src/Controller/EmptyController.php](../src/Controller/EmptyController.php)
- [src/Entity/UserRegistratedCorporate.php](../src/Entity/UserRegistratedCorporate.php)
- [src/Repository/UserRegistratedCorporateRepository.php](../src/Repository/UserRegistratedCorporateRepository.php)
- [src/Service/Account/AccountRequestMapper.php](../src/Service/Account/AccountRequestMapper.php)
- [src/Service/Notifier/NotifierService.php](../src/Service/Notifier/NotifierService.php)
- [src/Service/AccessRegistry/CredentialHubResolver/FilterService.php](../src/Service/AccessRegistry/CredentialHubResolver/FilterService.php)

**Megjegyzés:**
- a publikus API mezőnevek átnevezése csak kompatibilitási réteggel történhet
- belső symbol rename külön lépésekben javasolt

### 7. Legacy kommentek és TODO-k kitakarítása

**Mi a gond:**
- még maradtak architekturális és security-jellegű TODO-k a kódban

**Érintett példák:**
- [src/Service/Identity/IdentityService.php](../src/Service/Identity/IdentityService.php)
- [src/Service/AuthBridge/AuthBridgeHandler/Application/Fetch.php](../src/Service/AuthBridge/AuthBridgeHandler/Application/Fetch.php)

**Teendő:**
- backlog tétellé alakítani
- a kódban csak lokális implementációs komment maradjon

### 8. Kontroller-válasz policy teljes egységesítése

**Mi a gond:**
- a repository egészében még vegyesen él a raw `new JsonResponse(...)` és az enveloped helperes válasz

**Érintett példák:**
- [src/Controller/DeviceManagement/Restore/RestoreController.php](../src/Controller/DeviceManagement/Restore/RestoreController.php)
- [src/Controller/DeviceManagement/Nfc/NfcController.php](../src/Controller/DeviceManagement/Nfc/NfcController.php)
- [src/Controller/DeviceManagement/Identity/IdentityController.php](../src/Controller/DeviceManagement/Identity/IdentityController.php)
- [src/Controller/Account/AccountController.php](../src/Controller/Account/AccountController.php)

**Megjegyzés:**
- csak inventory testek mellett szabad hozzányúlni
- ahol publikus contract őrzi a shape-et, ott adapter réteg kell

### 9. Domain nyelvezet egységesítése

**Mi a gond:**
- a repositoryban keveredik a `domain`, `application`, `credential`, `page`, `registration`, `process` szóhasználat

**Érintett példák:**
- [src/Service/AccessRegistry/AccessRegistryDomainService.php](../src/Service/AccessRegistry/AccessRegistryDomainService.php)
- [src/Service/AccessRegistry/CredentialHubResolver/CheckService.php](../src/Service/AccessRegistry/CredentialHubResolver/CheckService.php)
- [src/Service/AuthBridge/AuthBridgeHandler/Application/Fetch.php](../src/Service/AuthBridge/AuthBridgeHandler/Application/Fetch.php)

## P3 – második körös finomítások

### 10. `ContainerBagInterface` és `ParameterBagInterface` függések kivezetése

**Mi a gond:**
- túl sok service globális paraméterelérésből dolgozik
- ez gyenge kohéziót és nehezebb tesztelhetőséget okoz

**Érintett példák:**
- [src/Service/Corporate/CorporateRegistrationService.php](../src/Service/Corporate/CorporateRegistrationService.php)
- [src/Service/Firebase/FirebaseService.php](../src/Service/Firebase/FirebaseService.php)
- [src/Service/Crypters/CrypterService.php](../src/Service/Crypters/CrypterService.php)
- [src/Service/Restore/RestoreService.php](../src/Service/Restore/RestoreService.php)
- [src/Service/Hmac/HmacValidator.php](../src/Service/Hmac/HmacValidator.php)
- [src/Service/Hmac/ListenerHmacPolicy.php](../src/Service/Hmac/ListenerHmacPolicy.php)
- [src/Helper/AuthorizationHelperFactory.php](../src/Helper/AuthorizationHelperFactory.php)
- [src/Service/Notifier/NotifierService.php](../src/Service/Notifier/NotifierService.php)

**Cél:**
- bounded-context specifikus config objectek

### 11. Reflection alapú inventory tesztek közös helperbe húzása

**Érintett példák:**
- [tests/Config/RoutePolicyInventoryTest.php](../tests/Config/RoutePolicyInventoryTest.php)
- [tests/Config/ProtectedApiChannelPolicyInventoryTest.php](../tests/Config/ProtectedApiChannelPolicyInventoryTest.php)

### 12. Minőségi kapu bővítése

**Érintett:**
- [composer.json](../composer.json)
- [/.github/workflows/deploy.yml](../.github/workflows/deploy.yml)

**Teendő:**
- `phpstan` vagy `psalm`
- külön CI workflow: test, lint, static analysis

## Legkockázatosabb területek

1. Corporate follow-up / registration belső hibakezelés
2. Credential Hub raw `array`-t visszaadó service-ek
3. Crypter + HMAC + payload envelope réteg
4. Globális config-elérés a service-ekben
5. Naming és domain-nyelvi inkonzisztencia

## Javasolt végrehajtási sorrend

1. Corporate / Credential Hub belső error-flow egységesítés, egy service-enként
2. Credential Hub result DTO-k bevezetése, egy raw payload service-enként
3. Payload / HMAC / envelope réteg tovább-szűkítése
4. Naming cleanup a belső symbolokon
5. `ContainerBagInterface` kiváltása config objectekre
6. Response helper explicitálása
7. Static analysis és CI bővítés

## Ajánlott következő konkrét TDD lépések

1. [src/DTO/QR/CredentialHubIdentityDTO.php](../src/DTO/QR/CredentialHubIdentityDTO.php)

## Záró értékelés

A repository jelenleg **stabil, de közepes technikai adóssággal terhelt**. A legfontosabb teendő nem új feature, hanem a belső szerkezet egységesítése:

- typed error-flow
- typed result DTO-k
- szűkebb service contractok
- kevesebb globális config-függés
- tisztább domain nyelvezet

Ezek a refaktorok a jelenlegi tesztháló mellett biztonságosan végrehajthatók, de csak **szigorúan kis lépésekben**, teljes TDD folyamattal.

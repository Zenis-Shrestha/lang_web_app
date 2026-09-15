# Owner PII Encryption – Technical Response

## Proposed Approach

We will encrypt the sensitive fields in `building_info.owners`—`owner_name`, `owner_gender`, `owner_contact`, and `nid`—using **AES-256-GCM** through Laravel's `Illuminate\Encryption\Encrypter`. The encrypted values will remain in the same existing columns, which will be converted to `TEXT`. Each encrypted value will use a version prefix:

```text
enc:v1:<encrypted payload>
```

AES-256-GCM is recommended because it uses a strong 256-bit key and provides authenticated encryption: it protects confidentiality and detects modified or corrupted ciphertext. It also uses a random nonce, so encrypting the same value twice produces different ciphertext. This reduces information leakage from repeated names, contacts, or NIDs. Laravel 8.83.29 already supports this cipher, so we can use a tested framework component instead of implementing cryptography ourselves.

Laravel's `APP_KEY` will not be used. A separate random 32-byte PII key will be stored in a protected server file outside the repository, public folders, PostgreSQL, and application logs. Laravel will receive only the file location through `PII_KEY_FILE`. The key will never be sent to the browser.

## Encryption, Decryption, and Masking

Validated input will be encrypted immediately before persistence. `null` will remain `null`, and values already beginning with `enc:v1:` will not be encrypted again. Existing records will be migrated in restartable batches. During migration, legacy plaintext can be read temporarily, but this compatibility behavior will be removed after verification.

Normal pages and API responses should return masked values, for example `98******67`, rather than raw ciphertext. Plaintext decryption will require a dedicated permission and successful reauthentication. After reauthentication, the server may grant a five-minute PII access session. The session will store only an expiry timestamp—not the encryption key or decrypted data.

The user's password or PIN is an authorization credential, not the PII encryption key. It should be verified using Laravel password hashing (`Hash::check`). We must not encrypt the entered PIN and compare ciphertext because randomized encryption intentionally produces different ciphertext for identical inputs.

## Future Per-User PIN for Viewing and Exporting PII

AES-256-GCM fully supports a future feature in which selected users enter a PIN before viewing or exporting owner PII. The PIN will control authorization; it will not encrypt or decrypt the database directly. Each authorized user should have an individual PIN stored only as an Argon2id or bcrypt hash. After Laravel verifies the PIN with `Hash::check`, confirms the relevant `View Owner PII` or `Export Owner PII` permission, and passes rate-limit checks, it will create a server-side PII-unlock session lasting approximately five minutes. During that period, the server will use the protected AES-256-GCM key to decrypt only the permitted records. Access will expire automatically and can also be ended with a manual “Lock PII” action.

```text
Authorized user selects View/Export PII
    -> enters personal PIN
    -> Laravel verifies the PIN hash and permission
    -> five-minute server-side unlock is created
    -> server decrypts permitted PII using the protected AES key
    -> access expires automatically
```

The PIN and PII encryption key must remain separate. A short PIN is vulnerable to brute-force guessing and must never be used or derived as the AES key. Changing or forgetting a PIN must not make database records undecryptable. PIN attempts should be rate-limited with temporary lockout, and unlock, view, export, failure, and manual-lock actions should be audited without recording PINs, keys, or plaintext PII. Decrypted exports must be streamed or stored only in protected short-lived storage and must never be placed in a public directory.

Direct owner searches using `LIKE` or `ILIKE` will stop working after randomized encryption. These searches should be removed from general pages in the first phase. Required encrypted searching will need a later, separately approved blind-index/HMAC design; the application must not decrypt the entire table to search.

## Why Use a Service Class Instead of a Global Helper

The implementation should use a dedicated `app/Services/PiiEncryptionService.php` with `encrypt`, `decrypt`, and `isEncrypted` methods. A global helper should not contain the encryption logic.

A service class is safer because it creates one controlled cryptographic boundary, loads and validates the dedicated key in one place, supports dependency injection, and can fail securely when the key or ciphertext is invalid. It is straightforward to unit-test and mock, and its explicit use makes security-sensitive encryption and decryption visible during code review. It also supports future ciphertext versions and key rotation without changing every caller.

A global helper introduces hidden dependencies, can be called from anywhere without clear authorization context, is harder to mock and test, and encourages accidental decryption in views, exports, logs, or API serialization. Duplicating key loading or encryption rules inside helpers also increases the chance of inconsistent behavior and plaintext leakage. If convenience wrappers are ever added, they should only delegate to the service; they must never implement cryptography, load keys, or make authorization decisions themselves.

This design separates responsibilities clearly: `PiiEncryptionService` performs encryption and decryption; middleware or an access-control service decides whether plaintext access is permitted; and a presenter/resource applies masking. This provides secure encrypted storage while supporting controlled five-minute viewing and protected PII exports.

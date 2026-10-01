# PII Module Encryption — Junior Developer Guide

This guide explains the safe order for adding encrypted PII to another Laravel
module. Follow the order; encryption is not only a model change. Every write,
read, filter, export, API, report, map layer, log, and backup path must be
reviewed.

## 1. Define the scope before coding

Write down:

1. Database table and schema, for example `fsm.applications`.
2. Exact PII columns, for example `customer_name` and `customer_contact`.
3. Every write path: form create/update, CSV import, API, job, command, and raw
   SQL.
4. Every read path: list, show, edit, lookup, report, export, map, API, and
   DataTables JSON.
5. Which roles may view, unlock, edit, and export the PII.

Use the existing external PII key. Do not generate a separate key for every
module unless the security architecture specifically requires key separation.

## 2. Make encrypted columns large enough

AES-256-GCM ciphertext is longer than plaintext. Add a migration changing each
PII column to `TEXT`.

```php
DB::statement(
    'ALTER TABLE schema.table_name '
    . 'ALTER COLUMN pii_column TYPE TEXT USING pii_column::TEXT'
);
```

Do not provide an automatic `down()` migration that silently truncates or
decrypts data. Require a reviewed manual rollback.

## 3. Encrypt every normal write

Add model setters that call `PiiEncryptionService`:

```php
public function setOwnerNameAttribute($value): void
{
    $this->attributes['owner_name'] = app(PiiEncryptionService::class)
        ->encrypt($value === null ? null : (string) $value);
}
```

The encryption service already preserves `NULL` and prevents double
encryption. Confirm that CSV imports and APIs create or update the Eloquent
model. Raw `DB::table()->insert()` and database functions bypass model setters
and must explicitly encrypt before writing.

## 4. Never decrypt automatically in model getters

Do not add an accessor that decrypts PII whenever the model is read. That can
leak plaintext into logs, JSON serialization, reports, queues, and debugging
tools.

Create a module presenter/service with explicit methods:

- locked presentation: `********`
- authorized presentation: decrypt with `PiiEncryptionService`

## 5. Add module-specific authorization

Create separate permissions for the module:

- `View <Module> PII`
- `Unlock <Module> PII`
- `Export <Module> PII`

Create a seeder with `Permission::updateOrCreate()`. Do not automatically grant
the permissions to Guest or every existing role. Assign them only after role
review.

## 6. Add password-confirmed temporary access

Create a module-specific access service. Its session grant should contain:

- authenticated user ID
- expiry timestamp, normally five minutes
- approved scope or record ID when relevant

The unlock controller must check login, module list/view permissions, current
password, rate limiting, and CSRF. Record success and failure in the PII audit
log. A list unlock must not automatically unlock unrelated modules.

## 7. Protect list and network responses

Select only columns the page actually uses. Do not return nested models or
`table.*` when they contain PII.

When locked, replace the selected ciphertext with `********` on the server.
Never send ciphertext to the browser as a masking strategy. When unlocked,
decrypt only after permission and session checks. Apply `pii.no-cache` to
responses containing or conditionally containing PII.

Randomized AES-GCM cannot use SQL `LIKE` on encrypted values. Hide PII filters
while locked. For small temporary datasets, authorized filtering can decrypt
and compare in PHP. For large datasets, design a keyed blind index separately.

## 8. Separate ordinary and PII exports

Ordinary exports must not contain PII. Add a dedicated PII export that requires:

1. Export permission.
2. Current login password.
3. A validated CSV of record identifiers, such as `bin`, `application_id`, or
   `tax_code`.
4. Server-side validation even when the browser already validates the CSV.
5. Spreadsheet-formula escaping.
6. Audit logging with counts only—never PII values.
7. `Cache-Control: no-store` response headers.

Use a generic result such as `not_found_or_unauthorized` when row-level access
exists, so exports cannot discover records outside the user's scope.

## 9. Remove PII from derived and public surfaces

Inspect database views, materialized/status tables, GeoServer layers, map info
tools, reports, revision tables, application logs, queue payloads, and browser
storage. Prefer removing PII from derived/map tables entirely. Join the
encrypted source table only inside an authorized server request.

Never store decrypted PII in URLs, cookies, `localStorage`, audit context, or
application logs.

## 10. Add backfill and verification commands

Create two commands:

```text
pii:encrypt-existing-<module> --dry-run
pii:encrypt-existing-<module>
pii:verify-<module>-encryption
```

The backfill must skip `NULL` and already encrypted values, work in chunks and
transactions, and never print PII. The verification command should report only
counts for encrypted, plaintext, and invalid fields.

Register both commands in `app/Console/Kernel.php`.

## 11. Add tests

At minimum test:

- model setters encrypt every PII field;
- values are not double encrypted;
- presenter masks while locked and decrypts explicitly;
- module unlock is user-scoped, expires, and can be revoked;
- CSV parser validates the identifier header and deduplicates rows;
- locked JSON contains neither plaintext nor ciphertext;
- wrong password and missing permission are blocked;
- ordinary exports contain no PII.

Run syntax, focused tests, Blade compilation, and a diff check:

```bash
php -l path/to/changed-file.php
php artisan test tests/Unit/ModulePiiTest.php
php artisan view:cache
git diff --check
```

## 12. Deployment order

Back up the database and external PII key first.

```bash
php artisan migrate
php artisan db:seed --class=ModulePiiPermissionsSeeder
php artisan permission:cache-reset
php artisan config:clear
php artisan pii:check-configuration
php artisan pii:encrypt-existing-<module> --dry-run
php artisan pii:encrypt-existing-<module>
php artisan pii:verify-<module>-encryption
php artisan test tests/Unit/ModulePiiTest.php
```

After verification, assign permissions to approved roles and test locked list,
network response, reveal, timeout, manual lock, wrong password, import, API,
ordinary export, dedicated export, reports, and maps.

Set `PII_ALLOW_LEGACY_PLAINTEXT=false` only after every in-scope PII table has
been successfully backfilled and verified.

## 13. Main code locations in this project

| Concern | Location |
|---|---|
| Key and cipher configuration | `config/pii.php`, `.env` |
| Encryption engine | `app/Services/PiiEncryptionService.php` |
| External key providers | `app/Services/PiiKeys/`, `app/Contracts/` |
| Model write encryption | `app/Models/<Module>/...` |
| Mask/decrypt presentation | `app/Services/<Module>PiiPresenter.php` |
| Temporary access state | `app/Services/<Module>PiiAccessService.php` |
| Password and permission checks | `app/Http/Controllers/<Module>/...PiiAccessController.php` |
| Dedicated export | `...PiiExportController.php`, `...PiiExportService.php` |
| Permissions | `database/seeders/<Module>PiiPermissionsSeeder.php` |
| Existing-data migration | `app/Console/Commands/EncryptExisting...php` |
| Verification | `app/Console/Commands/Verify...php` |
| No-cache middleware | `app/Http/Middleware/PreventPiiResponseCaching.php` |
| Audit logging | `app/Services/OwnerPiiAuditService.php` |
| UI | `resources/views/<module>/...` |
| Routes | `routes/web.php`, and `routes/api.php` when applicable |

## 14. Git checklist

Before committing:

```bash
git status --short
git diff
git diff --check
git diff --cached --name-status
git diff --cached
```

Do not commit `.env`, PII key files, exported PII CSVs, database backups, or
screenshots containing plaintext PII. `.env.example` may contain variable names
and safe defaults, but never real secrets.

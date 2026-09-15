# Owner PII Encryption: Implementation Guide, Impact Analysis, and Estimate

**Application:** Laravel 8 / PostgreSQL  
**Primary table:** `building_info.owners`  
**Prepared:** 2026-09-04  
**Delivery assumption:** One developer working full-time

## 1. Purpose

This document explains the work required to:

- Encrypt owner PII at rest in the existing columns of `building_info.owners`.
- Mask owner PII in normal application screens and responses.
- Allow specifically authorized users to unlock PII for five minutes after reauthentication.
- Provide a controlled “Export PII Information” function with building filters.
- Prevent application, API, import, export, revision, map, and database-function paths from bypassing encryption.
- Migrate existing owner records safely and provide backup, verification, and rollback procedures.

This is both an implementation guide and a code/database impact analysis. It does not authorize a production migration by itself.

## 2. Executive Estimate

### Recommended planning estimate

For one developer, the expanded scope is estimated at **16–22 working days**, or approximately **128–176 engineering hours**.

A realistic project commitment is **four calendar weeks**, assuming prompt code review, access to a staging environment, and no major surprises in GeoServer/WFS or production database functions.

| Phase | Estimated effort |
|---|---:|
| Confirm scope and complete dependency audit | 1.5–2.5 days |
| Security and access-flow design | 1–1.5 days |
| Encryption service and key configuration | 1.5–2 days |
| Database migration and backfill command | 1.5–2 days |
| Application/API/import write paths | 2–3 days |
| Read paths, masking, and authorization | 2.5–3.5 days |
| Five-minute unlock and protected PII export | 2–3 days |
| Revision history, database functions, and map/WFS handling | 1.5–2.5 days |
| Automated and manual testing | 2.5–3.5 days |
| Staging rollout, verification, documentation, and fixes | 1–2 days |
| **Total** | **16–22 days** |

### Smaller original scope

If the work is limited to encryption/decryption of `building_info.owners`, migration of existing records, and removal of broken searches—without masking, five-minute unlock, protected export, or broader access controls—the estimate is approximately **8–12 working days**.

### Estimate exclusions

The estimate does not include:

- Encrypting PII duplicated in every tax, SWM, water-supply, FSM, or reporting table.
- Implementing partial encrypted-name search.
- Adding HMAC/blind-index search fields.
- Major GeoServer customization.
- Infrastructure procurement or a new external key-management service.
- Waiting time for requirements approval, security review, deployment approval, or backup restoration testing.
- Remediation of unrelated existing defects.

Encrypting PII across all payment and operational schemas should be treated as a separate follow-up project after a full data-classification exercise. A preliminary allowance would be **an additional 3–7 days**, but it cannot be estimated reliably until those schemas and integrations are inspected.

## 3. Assumptions and Decisions

This plan uses the following assumptions:

1. The confirmed target is `building_info.owners`, not `buildings.owners`.
2. The encrypted columns remain the existing columns; no `_enc` duplicates will be created.
3. The target PII fields are `owner_name`, `owner_gender`, `owner_contact`, and `nid`.
4. `bin` remains plaintext because it is the owner-to-building relationship key.
5. Laravel's `APP_KEY` will not be used or regenerated.
6. A dedicated 256-bit PII encryption key will be stored in a protected server file.
7. `PII_KEY_FILE` will point Laravel to the protected key file.
8. Encryption will be randomized and versioned with the `enc:v1:` prefix.
9. Normal screens and API responses will return masked PII or omit it.
10. Users need explicit permission and successful reauthentication to see or export plaintext PII.
11. Successful reauthentication creates a server-side authorization window lasting five minutes.
12. The encryption key is never sent to the browser.
13. Owner-name partial searches will be disabled in phase one.
14. Production encryption will not run without a verified backup and a separately recoverable encryption key.

## 4. Security Design

### 4.1 Encryption at rest

Use Laravel's `Illuminate\Encryption\Encrypter` initialized with a dedicated 32-byte key. For this Laravel version, use a framework-supported authenticated encryption configuration such as `AES-256-CBC`.

Stored values will have this format:

```text
enc:v1:<Laravel encrypted payload>
```

Rules:

- `null` remains `null`.
- A value beginning with `enc:v1:` is not encrypted again.
- Legacy plaintext remains readable only during the migration compatibility period.
- Invalid or corrupted ciphertext must fail safely and must never be returned as plaintext.
- Exceptions and logs must not contain PII, ciphertext payloads, or key material.

### 4.2 Key storage

Recommended server configuration:

```text
PII_KEY_FILE=/etc/<application-name>/secrets/pii.key
```

The file should:

- Contain a securely generated base64-encoded 32-byte key.
- Be outside the repository and public web directory.
- Be readable only by the application service account.
- Be backed up securely and separately from the database.
- Be deployed to every application worker that must encrypt or decrypt owner PII.

The application must refuse to perform PII operations if the file is missing, malformed, too permissive, or contains an invalid key.

### 4.3 Authentication versus encryption key

The user's password or dedicated PIN is an authorization factor. It is not the encryption key.

Do not encrypt the entered PIN and compare ciphertext. Randomized encryption creates different ciphertext for identical inputs.

Recommended approach:

1. Require the authenticated user's current account password.
2. Verify it using Laravel's password hashing facilities.
3. Confirm the user also has the required PII permission.
4. Store only a short-lived server-side session flag and expiry time.
5. Continue using the protected server-side PII key for decryption.

If a separate PII PIN is mandatory, store only an Argon2id or bcrypt hash and verify it with `Hash::check()`. Never store a reversible PIN.

### 4.4 Five-minute unlock

Recommended flow:

```text
Authenticated user
    -> PII permission check
    -> reauthentication/password check
    -> rate-limit check
    -> create server-side unlock timestamp
    -> allow plaintext view/export for five minutes
    -> automatic expiry
```

The session should contain an expiry timestamp such as `pii_unlocked_until`, not the encryption key. Every plaintext PII endpoint must check both permission and expiry.

The five-minute period should expire on logout, password change, session invalidation, or explicit “Lock PII” action.

### 4.5 Masking

Masking controls presentation; encryption protects storage.

Suggested default masks:

| Field | Example plaintext | Masked example |
|---|---|---|
| Owner name | `Ram Bahadur` | `R** B******` |
| Contact | `9841234567` | `98******67` |
| NID | `123456789` | `12*****89` |
| Gender | `Male` | Omit or display only where operationally required |

Masking should happen through a dedicated presenter/resource/service after authorized decryption. Never treat `enc:v1:...` as a user-facing masked value.

### 4.6 Permissions

Use separate permissions so access can be assigned independently:

- `View Masked Owner PII`
- `Unlock Owner PII`
- `Export Owner PII`

Viewing plaintext and exporting plaintext should not be covered by a general building-list or building-export permission.

### 4.7 Audit logging

Recommended audit events:

- Successful and failed PII unlock attempts.
- Manual lock and automatic expiry.
- Plaintext owner view.
- PII export request and completion.
- Filters used, row count, user ID, timestamp, IP, and request identifier.

Audit logs must not include decrypted names, contacts, NIDs, encryption keys, PINs, passwords, or complete exported files.

## 5. Database Impact

### 5.1 Required change: `building_info.owners`

The local database contains 15,994 owner rows. Required type changes are:

| Column | Current type | New type | Reason |
|---|---|---|---|
| `owner_name` | `VARCHAR(255)` | `TEXT` | Ciphertext exceeds plaintext length |
| `owner_gender` | `VARCHAR(255)` | `TEXT` | Ciphertext exceeds plaintext length |
| `owner_contact` | `BIGINT` | `TEXT` | Numeric type cannot hold ciphertext |
| `nid` | Unbounded `VARCHAR` | `TEXT` | Normalize encrypted storage |

The column names, nullability, and owner relationship remain unchanged.

The following are not changed:

- `id` primary key.
- `bin` foreign key to `building_info.buildings(bin)`.
- Unique constraint on `bin`.
- Timestamp fields.

There are two apparently redundant unique indexes on `bin`. They are unrelated to PII encryption and should not be modified in this change unless separately approved.

### 5.2 Required review: `revisions`

The `Owner` model uses Venturecraft revision tracking. The local database already contains an owner-name revision with non-empty old and new values.

Required actions:

- Stop future plaintext owner values from entering `revisions`.
- Choose a retention treatment for existing owner PII revisions:
  - Encrypt relevant `old_value` and `new_value` values with the PII service; or
  - Permanently redact/delete those values under an approved retention policy.
- Back up and test the selected remediation.

Recommended default: exclude the four encrypted PII attributes from generic revision storage and write non-sensitive audit events instead. Generic history screens should not automatically reveal historical PII.

### 5.3 Recommended new table: `security.pii_access_audits`

For accountable access, add a non-PII audit table. A possible structure is:

| Column | Purpose |
|---|---|
| `id` | Primary key |
| `user_id` | Actor |
| `action` | Unlock, lock, view, export, or failure |
| `target_type` | Building, owner, or export |
| `target_reference` | Non-PII record identifier such as owner ID or BIN |
| `filters` | Sanitized JSON containing non-PII export filters |
| `row_count` | Number of records returned/exported |
| `ip_address` | Request origin |
| `request_id` | Correlation identifier |
| `created_at` | Event timestamp |

Do not store owner names, contacts, NIDs, plaintext exports, passwords, PINs, or encryption keys in this table.

If project policy does not allow a new audit table, emit structured security events to an approved protected audit system. Ordinary application logs are not an adequate substitute unless access and retention are controlled.

### 5.4 Permissions tables

The application already uses permission middleware. Permission seeders and the existing permissions/role-assignment tables will be affected by adding the three PII permissions.

No PII should be added to those tables.

### 5.5 Session storage

If the application uses server-side/database sessions, the five-minute authorization expiry may be stored in the existing session payload. No new session table column is required.

The session must store only authorization state and expiry—not the PII key or decrypted values.

### 5.6 Payment and operational tables

The following integrations contain or reference owner-like PII and require auditing:

- `taxpayment_info.tax_payment_status`
- `watersupply_info.watersupply_payment_status`
- Tax CSV import tables/models containing `owner_name` and `owner_contact`
- SWM payment imports and status/reporting data
- FSM application/customer fields populated from building owners

Phase-one requirements:

- Determine whether each integration writes back to `building_info.owners`.
- Ensure any owner-table write passes through the encryption boundary.
- Prevent plaintext synchronization functions from updating encrypted owner columns.
- Document duplicated PII that remains outside `building_info.owners`.

Encrypting these other schemas is not automatically included in the owner-table scope. Leaving duplicate plaintext PII elsewhere means the system does not yet have system-wide PII-at-rest protection; this must be explicitly recorded as residual risk.

### 5.7 PostgreSQL functions

Two inspected functions write plaintext owner data directly:

- `taxpayment_info.fnc_insrtupd_taxbuildowner`
- `watersupply_info.fnc_insrtupd_taxbuildowner`

They also reference a `tax_code` column absent from the inspected owner table, indicating that they may be stale.

Before migration:

1. Identify their schedules, callers, owners, and last execution evidence.
2. Disable or remove stale callers.
3. Do not put the encryption key inside PostgreSQL.
4. Replace required synchronization with an authorized Laravel job/service that encrypts before writing.
5. Verify that no database job can overwrite ciphertext with plaintext.

## 6. Application Code Impact

### 6.1 New components

Expected new components include:

- `config/pii.php`
- `app/Services/PiiEncryptionService.php`
- `app/Services/OwnerPiiPresenter.php` or equivalent masking/decryption boundary
- `app/Http/Middleware/RequirePiiUnlock.php`
- `app/Http/Controllers/BuildingInfo/OwnerPiiAccessController.php`
- `app/Http/Controllers/BuildingInfo/OwnerPiiExportController.php`
- Form request classes for unlock and export filters
- `app/Console/Commands/EncryptOwnerPii.php`
- Migration for owner column types
- Migration for access-audit storage, if approved
- Permission seeders
- Unit, feature, and command tests

Names may be adjusted to match repository conventions.

### 6.2 Owner model

`app/Models/BuildingInfo/Owner.php` requires changes to:

- Define the four encrypted attributes centrally.
- Encrypt plaintext before persistence.
- Preserve `null`.
- Avoid double encryption.
- Provide controlled decryption rather than unconditional serialization of plaintext.
- Prevent plaintext revision-history entries.
- Prevent accidental plaintext exposure through `toArray()` and JSON responses.

A model getter that always returns plaintext is convenient but weakens access control because any serialization can reveal PII. The safer design is:

- Keep raw model attributes encrypted.
- Use explicit service methods for authorized decryption.
- Use a presenter/resource for masked output.
- Never append decrypted attributes globally.

### 6.3 Known application write paths

Known write paths requiring coverage include:

- `app/Services/BuildingInfo/BuildingStructureService.php`
- `app/Services/Fsm/ApplicationService.php`
- Building create and update controllers that call the building service
- Containment workflows that update buildings/owners through the shared service
- API endpoints that reuse building or containment services
- Imports or jobs that instantiate/update `Owner`
- Raw `DB::table('building_info.owners')` operations
- Database-side functions and scheduled jobs

Every write should either use the `Owner` encryption boundary or call `PiiEncryptionService` explicitly before a raw update.

### 6.4 CSV import impact

Tax, SWM, and water-supply CSV imports must be traced from upload through validation, staging/status tables, synchronization jobs, and reports.

For each import:

1. Identify the destination table.
2. Determine whether imported owner data is copied into `building_info.owners`.
3. Confirm validation still accepts plaintext user input before encryption.
4. Encrypt only at the persistence boundary for the owners table.
5. Ensure validation or error logs do not include raw PII.
6. Ensure rejected-row files containing PII have protected storage and retention.

Importing into a separate payment table does not automatically require owner-table encryption logic. It does create a separate PII risk that must be documented.

### 6.5 Known read and export paths

Direct owner-table references were found in:

- `app/Exports/BuildingsEmptiedListExport.php`
- `app/Exports/BuildingsListExport.php`
- `app/Exports/BuildingsOwnerExport.php`
- `app/Exports/BuildingsRoadListExport.php`
- `app/Exports/PointBuildingsListExport.php`
- `app/Models/BuildingInfo/Owner.php`
- `app/Services/BuildingInfo/BuildingStructureService.php`
- `app/Services/Fsm/ContainmentService.php`

Additional consumers include building forms, FSM autofill, map responses, and WFS property requests.

Required behavior:

- General lists receive masked values or omit PII.
- Building pages show masked values until the user unlocks PII.
- Existing broad exports must exclude plaintext PII unless they use the protected export permission and unlock middleware.
- Raw query results must be passed through the presenter/decryption service.
- JSON responses must use explicit API resources or allowlists rather than `select('*')` or returning models directly.

### 6.6 Map and WFS impact

The map frontend directly requests `owner_name`, `owner_gender`, `owner_contact`, and `nid` through WFS/property lists. GeoServer/WFS bypasses Laravel and cannot apply Laravel authorization or decryption safely.

Required phase-one change:

- Remove owner PII properties from direct WFS requests and published layers.
- Fetch PII only through an authenticated Laravel endpoint after permission and unlock checks.
- Prefer returning masked information unless plaintext is specifically required.

`MapsController::getOwnerOfBuilding` currently returns an entire owner object and references a missing or stale `App\BuildOwner` class. Replace this with an explicit authorized resource response or remove the endpoint.

### 6.7 Search impact

The following owner-name searches use plaintext `ILIKE` and will break after randomized encryption:

- `app/Services/BuildingInfo/BuildingStructureService.php`, around inspected lines 808 and 1023.

Phase-one behavior:

- Remove/disable owner-name filtering in general building searches.
- Explain its temporary unavailability in the UI.
- Keep non-PII filters such as BIN, ward, house number, and road code.
- Do not decrypt all owner records to search.

Future exact-match search may use a separately approved HMAC blind index. Partial name search requires a separate security design and is excluded from this phase.

### 6.8 Protected PII export

The dedicated export should:

- Require `Export Owner PII` permission.
- Require an active five-minute PII unlock.
- Accept allowlisted filters such as BIN, ward, road code, or other non-PII building attributes.
- Select only approved columns.
- Decrypt records row-by-row or in bounded chunks.
- Stream the file without placing it in a public directory.
- Set no-cache and download headers.
- Audit the action and record count without recording PII.
- Avoid queued export jobs unless the job worker can securely access the key and the resulting file has protected storage, expiry, and one-time access controls.

For the first release, a synchronous, size-limited export is safer. Large export requirements should be separately designed.

## 7. Detailed Implementation Steps

### Phase 0: Confirm requirements and acceptance criteria

1. Confirm which roles receive each new PII permission.
2. Confirm whether current account password or a separate PIN is required.
3. Confirm the exact screens allowed to show plaintext PII.
4. Confirm allowable export filters and maximum export size.
5. Confirm the treatment of existing revision history: encrypt, redact, or delete.
6. Confirm whether payment-schema PII is explicitly out of scope.
7. Confirm GeoServer/WFS configuration ownership and deployment process.
8. Record acceptance criteria and obtain security/product approval.

**Deliverable:** Approved security behavior and scope boundary.

### Phase 1: Complete code and database dependency inventory

1. Search controllers, services, repositories, imports, jobs, commands, observers, model events, exports, views, API resources, and raw queries.
2. Trace building and containment API endpoints into their shared services.
3. Trace tax, SWM, and water-supply imports end-to-end.
4. Inspect scheduled tasks and PostgreSQL jobs/functions.
5. Inventory owner PII in logs, revision tables, caches, temporary files, rejected imports, and exports.
6. Confirm all direct WFS/GeoServer exposure.

**Deliverable:** Signed-off read/write/dependency matrix.

### Phase 2: Implement key configuration and encryption service

1. Add `config/pii.php` with key-file path, cipher, prefix, and unlock duration.
2. Validate configuration during service initialization.
3. Implement `encrypt(?string): ?string`.
4. Implement `decrypt(?string): ?string`.
5. Implement `isEncrypted(?string): bool`.
6. Add safe exception types and sanitized error messages.
7. Add unit tests for null, empty string, Unicode, long values, corruption, wrong key, version prefix, and double-encryption prevention.
8. Add a deployment-only key generation/runbook step; do not generate or commit a real key in source control.

**Deliverable:** Tested reusable encryption service.

### Phase 3: Apply schema migration

1. Create a migration changing all four PII columns to `TEXT`.
2. Test the migration against a restored local/staging database.
3. Confirm constraints and indexes remain valid.
4. Measure lock duration and decide whether a maintenance window is needed.
5. Document the migration rollback limitation: encrypted strings cannot be converted back to `BIGINT` without first decrypting `owner_contact`.

**Deliverable:** Rehearsed schema migration.

### Phase 4: Secure all writes

1. Add a central owner persistence boundary.
2. Update building create/update flows.
3. Update FSM application owner-update flows.
4. Confirm containment and API flows reuse the secured service.
5. Update any raw owner-table writes.
6. Disable/replace PostgreSQL plaintext synchronization functions.
7. Verify imports cannot bypass encryption.
8. Prevent revision tracking and logs from receiving plaintext.
9. Add feature tests that inspect raw database values and assert `enc:v1:` storage.

**Deliverable:** All new and updated owner PII stored encrypted.

### Phase 5: Implement masking and controlled reads

1. Implement consistent field-specific masks.
2. Prevent global model serialization from returning plaintext.
3. Update building forms and detail screens.
4. Update FSM autofill behavior according to approved authorization rules.
5. Update raw queries and exports.
6. Replace `select('*')` owner responses with explicit allowlists/resources.
7. Remove direct PII from WFS layers and map property lists.
8. Add tests for unauthenticated, unauthorized, masked, and authorized states.

**Deliverable:** Default application behavior exposes no unnecessary plaintext PII.

### Phase 6: Implement temporary unlock

1. Add permissions and role mappings.
2. Add unlock and lock endpoints.
3. Require password confirmation or verify the approved dedicated PIN hash.
4. Rate-limit failures and apply normal account/session protections.
5. Store `pii_unlocked_until` server-side for five minutes.
6. Add middleware that checks authentication, permission, and expiry.
7. Clear access on logout/session invalidation and provide manual lock.
8. Add sanitized audit events.
9. Add time-expiry and authorization tests.

**Deliverable:** Audited five-minute privileged PII access.

### Phase 7: Implement protected export

1. Build an allowlisted filter form on the building page.
2. Require export permission and active unlock.
3. Validate filters and maximum row count.
4. Query using non-PII filters.
5. Decrypt approved fields only.
6. Stream the export securely.
7. Add no-cache headers and avoid public storage.
8. Record export metadata in the security audit.
9. Add authorization, expiry, filter, size, and content tests.

**Deliverable:** Controlled “Export PII Information” function.

### Phase 8: Build the existing-record encryption command

Create an idempotent command such as:

```bash
php artisan owners:encrypt-pii
```

It must:

- Target only `building_info.owners`.
- Use `chunkById()` with a batch size near 500.
- Encrypt the same four existing columns.
- Preserve nulls.
- Skip `enc:v1:` values.
- Be safe to stop, restart, and run repeatedly.
- Avoid model getters that might hide the raw stored state.
- Avoid plaintext and ciphertext in output/logs.
- Report only sanitized counts and failures by record ID.
- Use transactions at an appropriate batch level.
- Fail closed if the key is unavailable.

Add a dry-run/count-only option if it can be implemented without reading PII into logs or output.

**Deliverable:** Restartable and tested backfill command.

### Phase 9: Handle historical revisions

1. Back up the `revisions` table.
2. Identify only revisions belonging to the Owner model and four PII keys.
3. Apply the approved encrypt/redact/delete policy.
4. Ensure history screens do not reveal plaintext.
5. Verify no targeted plaintext remains.

**Deliverable:** Approved historical PII remediation.

### Phase 10: Test and rehearse rollout

Required automated coverage:

- Service unit tests.
- Model/persistence tests.
- Building and FSM write-path tests.
- API authorization tests.
- Masking tests.
- Five-minute expiry tests using a controlled clock.
- Export permission and content tests.
- Migration and command idempotency tests.
- Null and legacy plaintext compatibility tests.
- Revision leak-prevention tests.
- Wrong/missing key failure tests.

Required manual coverage:

- Building create/edit/view.
- Containment-driven updates.
- FSM application autofill/update.
- Tax, SWM, and water-supply imports.
- Building lists and all owner-related exports.
- Map popups and WFS downloads.
- Owner-name filter removal.
- Permission assignment and session expiry.
- Backup restoration and key recovery.

**Deliverable:** Test evidence and staged release approval.

## 8. Deployment Runbook

### Before deployment

1. Freeze or identify all owner-write mechanisms.
2. Take a PostgreSQL backup.
3. Restore the backup in an isolated environment and verify it.
4. Generate and securely distribute the dedicated PII key.
5. Back up the key separately and test recovery.
6. Confirm application workers can read the key file.
7. Confirm plaintext PostgreSQL functions/jobs are disabled or replaced.
8. Deploy code with plaintext-read compatibility enabled.
9. Run automated smoke tests.

### Migration sequence

1. Enter the approved maintenance/read-only window if required.
2. Apply the column-type migration.
3. Deploy/enable secure write paths before allowing new owner writes.
4. Run `owners:encrypt-pii`.
5. Run sanitized verification queries.
6. Remediate revision history according to policy.
7. Verify new writes are encrypted.
8. Verify masked and authorized read behavior.
9. Verify protected export and audit events.
10. Re-enable normal traffic only after acceptance checks pass.

### Post-migration verification

Verify:

- Every non-null targeted owner value begins with `enc:v1:`.
- Null values remain null.
- The command can run again without changing already encrypted values.
- New creates and updates store ciphertext.
- Unauthorized responses never return plaintext or ciphertext payloads.
- Authorized, unlocked responses decrypt correctly.
- Access expires after five minutes.
- WFS/GeoServer no longer exposes owner PII.
- Search UI no longer offers a broken plaintext owner-name filter.
- No plaintext PII appears in revision history or logs.
- The key is absent from Git, PostgreSQL, logs, caches, and browser responses.

## 9. Rollback and Recovery

Application rollback and data rollback are different.

### Before existing records are encrypted

Code and schema changes may be rolled back conventionally, although `owner_contact` should not be converted back to `BIGINT` if any ciphertext exists.

### After existing records are encrypted

A normal migration rollback is unsafe. Safe recovery requires one of:

- Roll forward by fixing the application while preserving ciphertext and the key.
- Run a separately tested decrypt-back command before restoring old column types.
- Restore the verified database backup and redeploy the previous application version.

If the encryption key is lost, encrypted PII is unrecoverable. If the key is exposed, rotate it through a separately designed decrypt/re-encrypt process and investigate the security incident.

## 10. Risks and Mitigations

| Risk | Impact | Mitigation |
|---|---|---|
| Missing write path stores plaintext | High | Complete dependency matrix; central persistence boundary; raw DB tests |
| Key loss | Critical | Separate protected backups and recovery test |
| Key exposure | Critical | External secret file, least privilege, no browser/DB/log exposure |
| Revision history retains plaintext | High | Exclude PII and remediate existing entries |
| GeoServer/WFS exposes ciphertext or plaintext | High | Remove direct PII and route access through Laravel |
| Legacy export reveals PII | High | Remove fields or enforce dedicated export permission/unlock |
| Owner search stops working | Medium | Disable in phase one; design blind index later |
| Payment tables retain duplicate PII | High residual risk | Document scope and schedule broader data-classification project |
| Long schema lock or migration failure | Medium | Rehearse on staging copy and use maintenance window |
| Existing uncommitted changes conflict | Medium | Preserve changes, review diffs carefully, use focused commits |
| Decryption performed for excessive rows | Medium | Filter, paginate, chunk, and cap exports |
| Five-minute access reused by another person | Medium | Secure sessions, short expiry, manual lock, screen discipline, audit |

## 11. Acceptance Criteria

The work is complete only when:

1. The four owner PII fields are `TEXT` and encrypted in their existing columns.
2. No new owner write path can persist plaintext.
3. Existing records are encrypted idempotently.
4. Normal screens, APIs, maps, and exports do not expose plaintext PII.
5. Masking is consistent and tested.
6. Plaintext access requires authentication, explicit permission, and active five-minute unlock.
7. The encryption key never leaves the server secret boundary.
8. Protected exports enforce filters, authorization, limits, and audit logging.
9. Plaintext owner-name searches are removed or disabled.
10. Revision history has an approved and verified PII treatment.
11. PostgreSQL functions/jobs cannot overwrite encrypted owner values with plaintext.
12. Tax, SWM, water-supply, containment, FSM, and API flows have documented test results.
13. No plaintext PII or keys appear in application logs or Git.
14. Backup restoration and key recovery are proven before production migration.
15. A second command run performs no double encryption.

## 12. Suggested One-Developer Schedule

### Week 1

- Confirm requirements and permission model.
- Finish dependency inventory, including CSV/API paths.
- Implement and test key configuration and encryption service.
- Prepare and rehearse schema migration.

### Week 2

- Secure model/service/API/import write paths.
- Prevent revision leakage.
- Implement masking and safe serialization.
- Remove direct WFS PII exposure.

### Week 3

- Implement five-minute unlock.
- Implement dedicated protected PII export.
- Build the idempotent existing-record command.
- Update or disable database-side functions.

### Week 4

- Complete automated and manual testing.
- Rehearse backup, migration, verification, and rollback in staging.
- Fix integration issues.
- Prepare production checklist and release evidence.

This schedule assumes the developer can focus primarily on this work. Production deployment may occur later depending on review and maintenance-window availability.

## 13. Current Repository Consideration

Relevant files already contain uncommitted changes, including building controllers/models/services and map components. Implementation must preserve those changes and should use small, reviewable commits grouped by concern:

1. Configuration and encryption service.
2. Database migration and command.
3. Write-path protection.
4. Read masking and authorization.
5. Protected export.
6. Map/WFS and database-function remediation.
7. Tests and documentation.

## 14. Recommended Next Decision

Before coding begins, approve these four items:

1. Use the current account password for reauthentication rather than a separate PII PIN.
2. Add the three proposed PII permissions.
3. Add a dedicated non-PII access-audit table or approve an equivalent protected audit destination.
4. Confirm that payment-schema PII encryption is out of phase-one scope while all paths that write into `building_info.owners` remain in scope.

Once these decisions are approved, implementation can proceed without ambiguity.

# Owner PII Encryption Inspection Report

**Inspection environment:** Local development database  
**Target table:** `building_info.owners`  
**Inspection date:** 2026-08-27

No application code or database data was changed during this inspection.

## Recommended PII Scope

Encrypt these four columns in `building_info.owners`:

| Column | Current type | Null values | Recommendation |
|---|---:|---:|---|
| `owner_name` | `VARCHAR(255)` | 0 | Convert to `TEXT`; encrypt |
| `owner_gender` | `VARCHAR(255)` | 107 | Convert to `TEXT`; encrypt |
| `owner_contact` | `BIGINT` | 0 | Convert to `TEXT`; encrypt |
| `nid` | Unbounded `VARCHAR` | 15,990 | Convert to `TEXT`; encrypt |

The table contains 15,994 records, all of which are active. It does not have email or address columns.

The `bin` column should not be encrypted because it is the relationship key between owners and buildings.

## Table Structure

The complete inspected structure is:

| Column | Type | Nullable | Notes |
|---|---|---|---|
| `id` | `INTEGER` | No | Primary key; sequence-generated |
| `bin` | `VARCHAR(7)` | Yes | Foreign key and unique building reference |
| `owner_name` | `VARCHAR(255)` | Yes | PII |
| `owner_gender` | `VARCHAR(255)` | Yes | PII |
| `owner_contact` | `BIGINT` | Yes | PII; cannot hold ciphertext in its current type |
| `nid` | Unbounded `VARCHAR` | Yes | PII |
| `created_at` | Timestamp without time zone | Yes | Not PII for this implementation |
| `updated_at` | Timestamp without time zone | Yes | Not PII for this implementation |
| `deleted_at` | Timestamp without time zone | Yes | Not PII for this implementation |

## Constraints and Indexes

The table contains:

- Primary key `owners_id_pkey` on `id`.
- Foreign key `owners_bin_fkey` from `bin` to `building_info.buildings(bin)`.
- Unique constraint `owners_bin_unique` on `bin`.
- Unique index `owners_bin_idx` on `bin`.
- Unique index `owners_bin_unique` on `bin`.
- No indexes or unique constraints on the four PII columns.
- No table triggers.
- No database views or materialized views directly depending on the table.

There appear to be two redundant unique indexes on `bin`. This is not caused by the proposed encryption work and does not need to be changed as part of the first implementation.

Changing the four PII columns to `TEXT` will not directly affect the current constraints.

## Application Write Paths

The principal owner write locations are:

- `app/Services/BuildingInfo/BuildingStructureService.php`, which creates or updates `owner_name`, `owner_gender`, `owner_contact`, and `nid`.
- `app/Services/Fsm/ApplicationService.php`, which updates `owner_name`, `owner_gender`, and `owner_contact` from FSM application workflows.

Central encryption through the `Owner` model and `PiiEncryptionService` is the safest design because it covers both known application write paths and reduces the risk of accidental plaintext writes.

Encryption must occur before revision events or database writes. During migration, reads should support both:

- Values prefixed with `enc:v1:`, which must be decrypted.
- Legacy plaintext values, which must temporarily be returned unchanged.

## Application Read Paths

Model accessors alone are not sufficient. Several raw joins and exports return database values without constructing `Owner` model instances. Those results would expose ciphertext unless they are explicitly decrypted.

Direct `building_info.owners` references were found in these files:

- `app/Exports/BuildingsEmptiedListExport.php`
- `app/Exports/BuildingsListExport.php`
- `app/Exports/BuildingsOwnerExport.php`
- `app/Exports/BuildingsRoadListExport.php`
- `app/Exports/PointBuildingsListExport.php`
- `app/Models/BuildingInfo/Owner.php`
- `app/Services/BuildingInfo/BuildingStructureService.php`
- `app/Services/Fsm/ContainmentService.php`

Other consumers include:

- The building edit form.
- FSM application autofill workflows.
- Building list and owner exports.
- Map owner information responses.
- Geospatial/WFS layer property requests.

All authorized application paths that currently show owner information should continue to receive decrypted values. Authorization should be checked before decrypted PII is returned.

## Search Impact

Owner-name partial searches currently occur in `app/Services/BuildingInfo/BuildingStructureService.php` at the inspected locations around lines 808 and 1023.

Both searches use plaintext operations equivalent to:

```sql
owner_name ILIKE '%search value%'
```

Randomized encryption will make these searches unusable. Following the stated restrictions, the safest first implementation is to disable or report this owner-name filter rather than:

- Implement deterministic encryption.
- Implement HMAC or blind indexes in this phase.
- Decrypt the entire table to search.

No PII-based sorting, grouping, or `DISTINCT` operation was found.

## Revision History Risk

The `Owner` model enables Venturecraft revision tracking in `app/Models/BuildingInfo/Owner.php`.

The local `revisions` table already contains one `owner_name` revision with non-empty old and new values. Encrypting `building_info.owners` will not remove historical plaintext PII from the revision table.

The implementation should therefore:

- Ensure owner PII is encrypted before revision events execute; or
- Exclude encrypted owner attributes from revision tracking.
- Prevent future plaintext PII from entering revision history.
- Handle the existing historical revision data through a separate cleanup or encryption decision.
- Avoid logging plaintext values during cleanup.

## Owner API and Map Risks

`app/Http/Controllers/MapsController.php` contains a `getOwnerOfBuilding` endpoint that returns an entire owner object.

The controller imports `App\BuildOwner`, but no `BuildOwner` class was found in the repository. This path appears stale or incomplete. It should be corrected and its authorization reviewed before it is allowed to return decrypted owner PII.

The map frontend also requests owner fields directly from a geospatial/WFS layer. Once the database values are encrypted, this layer will return ciphertext because it bypasses Laravel and the encryption service.

The affected map output should be handled by one of these approaches:

1. Remove PII fields from direct WFS output.
2. Route authorized owner-data requests through Laravel so values can be decrypted safely.
3. Leave the affected owner fields unavailable until a secure integration is implemented.

Direct database or geospatial publication of decrypted PII is not recommended.

## Database-Side Functions

Two PostgreSQL functions reference `building_info.owners`:

- `taxpayment_info.fnc_insrtupd_taxbuildowner`
- `watersupply_info.fnc_insrtupd_taxbuildowner`

These functions insert or update plaintext owner name, gender, and contact data directly. They bypass Laravel and would bypass `PiiEncryptionService`.

They also reference a `tax_code` column that does not exist in the inspected `building_info.owners` table. This indicates that they are stale or currently unusable against the local schema.

These functions must not write to `building_info.owners` after encryption unless they are redesigned to use an approved encrypted application path. PostgreSQL should not receive or store the dedicated encryption key.

## Dedicated Key Configuration

The encryption implementation should use a separate random 256-bit key. Laravel's `APP_KEY` must not be reused or regenerated.

The agreed configuration is:

- Store the key in a protected file outside the repository and public folders.
- Set a `PII_KEY_FILE` environment variable to that file's absolute path.
- Load the key through Laravel configuration.
- Never store the key in PostgreSQL.
- Never hard-code the key in PHP.
- Never commit the key or its value to Git.
- Securely back up the key separately from the database backup.

## Safest Implementation Plan

1. Add a database migration converting all four PII columns to `TEXT` without renaming them.
2. Add `PII_KEY_FILE` configuration for a protected external key file.
3. Create `app/Services/PiiEncryptionService.php` using Laravel's `Illuminate\Encryption\Encrypter` and the dedicated key.
4. Implement `encrypt`, `decrypt`, and `isEncrypted` with the `enc:v1:` prefix.
5. Preserve `null` values and skip values already prefixed with `enc:v1:`.
6. Encrypt owner PII before model persistence and before revision tracking can store plaintext.
7. Add temporary read compatibility for legacy plaintext values.
8. Update raw joins, exports, map responses, FSM autofill, and other non-model read paths to decrypt authorized output explicitly.
9. Disable or clearly report owner-name filtering until a later blind-index/HMAC phase.
10. Create an idempotent `owners:encrypt-pii` Artisan command that targets only `building_info.owners`.
11. Process records using `chunkById()` with a batch size of approximately 500.
12. Ensure the command skips null and already-encrypted values and never logs plaintext PII.
13. Add tests for nulls, plaintext compatibility, encryption, decryption, double-encryption prevention, new writes, updates, batch migration, and repeated command execution.
14. Separately resolve historical revision PII and the two database-side write functions.
15. Test on development or staging.
16. Back up PostgreSQL, verify restoration, and securely back up the encryption key.
17. Run the production migration only after backup and recovery verification.
18. Verify that no targeted plaintext PII remains and that authorized reads still work.

## Implementation Restrictions

The implementation must not:

- Create duplicate or `_enc` columns.
- Rename existing PII columns.
- Use or regenerate Laravel's `APP_KEY`.
- Hash recoverable owner PII.
- Use deterministic encryption.
- Add HMAC or blind-index columns in this phase.
- Store the PII key in PostgreSQL or source code.
- Commit the PII key to Git.
- Double-encrypt values already prefixed with `enc:v1:`.
- Log plaintext PII.
- Decrypt the entire owner table to support searches.
- Run the production encryption command without a verified backup and recoverable key.

## Existing Worktree Changes

Several relevant files already contain uncommitted user changes, including:

- `app/Http/Controllers/BuildingInfo/BuildingController.php`
- `app/Models/BuildingInfo/Building.php`
- `app/Services/BuildingInfo/BuildingStructureService.php`
- `app/Http/Controllers/MapsController.php`
- `app/Services/Maps/MapsService.php`

These changes must be preserved carefully during implementation. They were not modified during this inspection.

## Final Recommendation

Proceed with encryption of `owner_name`, `owner_gender`, `owner_contact`, and `nid` in the existing columns of `building_info.owners`.

Before encrypting existing records, the implementation must cover every application write path, raw read/export path, revision-history behavior, and the direct map/WFS exposure. Owner-name search should be reported as temporarily unavailable until a later blind-index/HMAC solution is approved.

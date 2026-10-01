# Application Customer PII Encryption — Option 2 Workflow

## 1. Approved scope

This implementation protects only the customer/owner snapshot stored in
`fsm.applications`:

- `customer_name`
- `customer_gender`
- `customer_contact`

The following applicant fields remain plaintext and continue to support
Emptying, Feedback, Sludge Collection, and other operational FSM workflows:

- `applicant_name`
- `applicant_gender`
- `applicant_contact`

Customer and applicant are independent identities. They may contain the same
values for some applications, but equality is not assumed and the two groups
must not be automatically synchronized after creation.

> Accepted residual risk: applicant fields are still personally identifiable
> information. They remain visible to database readers, backups, reports, and
> authorized downstream workflows until a future applicant-PII phase is
> approved.

## 2. Data flow

```text
Building owner selected by BIN
          |
          | authorized one-record lookup
          v
Application create form
          |
          +--> customer_*  --> AES-256-GCM --> fsm.applications (encrypted)
          |
          +--> applicant_* ----------------> fsm.applications (plaintext)
                                                |
                                                +--> Emptying
                                                +--> Feedback
                                                +--> Sludge Collection
                                                +--> other approved FSM flows

Encrypted customer_* --> ApplicationCustomerPiiPresenter
                              |
                              +--> locked: ********
                              +--> unlocked: plaintext for five minutes
```

## 3. Encryption at rest

`App\Models\Fsm\Application` contains setters that encrypt customer values
before normal Eloquent inserts or updates. It reuses the existing external
PII key, key version, and `PiiEncryptionService` used by Building owner PII.

Stored values use the versioned format:

```text
enc:v1:<authenticated-ciphertext>
```

The algorithm is AES-256-GCM. `NULL` remains `NULL`, and an already encrypted
value is not encrypted twice. Applicant setters were deliberately not added.

## 4. List, filter, show, and edit behaviour

### Locked

- Customer name and contact in the Application data table return `********`.
- Customer gender and applicant fields are not added to the table response.
- Customer-name filtering is hidden and ignored.
- Show/edit customer fields receive only `********`.
- A crafted customer-PII update without an edit grant returns HTTP 403.

### Temporarily unlocked

The user must have both `View Application Customer PII` and
`Unlock Application Customer PII`, then re-enter the current login password.
The Application-specific session lasts five minutes and is separate from the
Building owner-PII session.

- List requests may decrypt customer name/contact.
- Customer-name filtering decrypts names in PHP and filters by matching IDs.
- Show requests may decrypt all three customer fields.
- Users with `Edit Application` receive the edit scope.
- Access and failures are written to the existing immutable PII audit log.
- Responses carrying PII use the `pii.no-cache` middleware.

## 5. BIN lookup

`ApplicationService::getBuildingDetails()` is always a non-PII address lookup.
After a BIN is selected, the create form shows `********` in the owner fields.
It does not inherit the five-minute Application-list reveal session.

An authorized user may click **View PII Information** and re-enter the current
login password. `ApplicationPiiAccessController::revealForCreate()` requires
`Add Application`, `View Application Customer PII`, and
`Unlock Application Customer PII`; it then decrypts only the owner attached to
the selected BIN. The response is audited and sent with no-store headers.

Changing the BIN clears the plaintext and returns the form to its masked
state. A late response for an earlier BIN is discarded. The **Same as Owner**
option remains disabled until the current BIN's owner has been revealed.

Customer PII is no longer written to or restored from browser `localStorage`.
The user must repeat the authorized lookup after the value leaves the current
form/page.

## 6. Applicant workflow

Downstream FSM modules continue to use `applicant_*`. When the create-form
autofill option is selected, the current customer values are copied once into
the applicant snapshot. Later edits do not imply that customer and applicant
are still the same person.

This phase does not encrypt, mask, or introduce new access permissions for
applicant fields.

## 7. Exports and history

The normal Application CSV no longer exports `customer_name`,
`customer_gender`, or `customer_contact`. Applicant columns remain because
they are part of the approved Option 2 operational scope.

A separate **Export Customer PII** action accepts a CSV with an
`application_id` header. It validates the list in the browser and again on
the server, requires the current login password, checks all three Application
PII permissions, preserves Application row-level visibility, writes an audit
event, and returns a non-cacheable CSV. Missing and inaccessible IDs both use
the status `not_found_or_unauthorized` so the export cannot enumerate records
outside the user's scope.

Customer fields are excluded from new revision-history records. Existing
revision tables must still be inspected separately for historical plaintext.

## 8. Main files and responsibilities

| File | Responsibility |
|---|---|
| `app/Models/Fsm/Application.php` | Encrypt customer fields and exclude them from revisions |
| `app/Services/ApplicationCustomerPiiPresenter.php` | Mask and explicitly decrypt Application customer PII |
| `app/Services/ApplicationPiiAccessService.php` | Separate five-minute Application PII session |
| `app/Http/Controllers/Fsm/ApplicationPiiAccessController.php` | Password confirmation, unlock, lock, and auditing |
| `app/Services/ApplicationCustomerPiiExportService.php` | Validate ID lists, enforce row visibility, decrypt, and prepare safe CSV rows |
| `app/Http/Controllers/Fsm/ApplicationCustomerPiiExportController.php` | Authorize, confirm password, audit, and stream the protected export |
| `app/Http/Controllers/Fsm/ApplicationController.php` | Enforce access on list, lookup, show, edit, and update |
| `app/Services/Fsm/ApplicationService.php` | Protected presentation, filter, BIN lookup, and non-PII export |
| `resources/views/fsm/applications/index.blade.php` | Reveal/lock UI and protected customer-name filter |
| `resources/views/fsm/applications/create.blade.php` | Mask create-form owner fields and perform the protected one-BIN reveal |
| `database/seeders/ApplicationPiiPermissionsSeeder.php` | Create Application PII permissions |
| `app/Console/Commands/EncryptExistingApplicationCustomerPii.php` | Dry-run and backfill existing customer values |
| `app/Console/Commands/VerifyApplicationCustomerPiiEncryption.php` | Verify encryption without printing PII |

## 9. Local/staging rollout

Back up the database and external `v1` PII key before the write migration.

```powershell
php artisan migrate
php artisan db:seed --class=ApplicationPiiPermissionsSeeder
php artisan permission:cache-reset
php artisan config:clear
php artisan pii:check-configuration
php artisan pii:encrypt-existing-applications --dry-run
php artisan pii:encrypt-existing-applications
php artisan pii:verify-application-encryption
php artisan test tests/Unit/ApplicationCustomerPiiTest.php
```

Assign the three new permissions only to approved roles. Do not grant them to
Guest. Test list, filter, show, edit, BIN lookup, normal export, timeout, manual
lock, wrong-password handling, and audit records before production rollout.

## 10. Follow-up work

1. Inspect existing revision records, logs, PDFs, and backups for historical
   customer plaintext.
2. Review applicant PII as a separate encryption phase.
3. Replace decrypt-and-scan name filtering with a keyed blind index if data
   volume makes the temporary search approach too slow.

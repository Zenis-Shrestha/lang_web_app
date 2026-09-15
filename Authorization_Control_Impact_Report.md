# Authorization Control Remediation - Impact and Development Changes

**Review audience:** Senior technical and security reviewers  
**Affected endpoints:** `/auth/users/{id}` and `/fsm/emptying/{id}`  
**Risk rating:** Medium  
**Status:** Code remediation completed; deployment migration and security verification required

## 1. Executive Summary

The application had incomplete server-side authorization on endpoints that display user and emptying-service records. Although the route groups required authentication, an authenticated Guest or insufficiently privileged user could directly request a record by changing the numeric ID in the URL.

This created an Insecure Direct Object Reference (IDOR) risk. A successful request could expose personal and operational information belonging to another user, service provider, or treatment plant.

The remediation introduces two mandatory authorization layers:

1. **Action-level permission:** the user must have explicit permission to perform the requested action, such as `View User` or `View Emptying`.
2. **Record-level scope:** the requested record must belong to an organization or operational scope the user is authorized to access.

Guest access to the Emptyings permission group is also removed. Existing deployments are corrected through a database migration so the fix does not depend only on rerunning seeders.

## 2. Security and Business Impact

### Before remediation

- Authentication was present on the route groups, but authentication alone did not prove that the user was authorized to view a specific record.
- `UserController::show()` did not enforce the `View User` permission.
- `EmptyingController` did not consistently apply its defined Emptying permissions to controller actions.
- The Guest role was explicitly granted List and View permissions for Emptying records.
- A user could change `{id}` in the browser and potentially retrieve another record.

### Potential business impact

- Disclosure of names, usernames, email addresses, roles, and account status.
- Disclosure of customer contact details and emptying-service information.
- Exposure of service-provider, treatment-plant, and municipal operational data.
- Privacy complaints, audit findings, and loss of confidence in access controls.
- Increased risk of automated record enumeration because the endpoints use numeric IDs.

### Expected impact after remediation

- Logged-out users remain blocked by authentication middleware.
- Guest users cannot list or view Emptying records.
- Users without the required action permission receive HTTP `403 Forbidden`.
- Users with a valid permission are still blocked when the requested record is outside their authorized scope.
- Approved municipal administrators retain broad operational access.
- Service-provider and treatment-plant users are restricted to their associated records.
- Missing records continue to return HTTP `404 Not Found`.

## 3. Development Changes

### A. User endpoint permission enforcement

**File:** `app/Http/Controllers/Auth/UserController.php`

The developer adds `permission:View User` middleware to the `show` action. Direct access to `/auth/users/{id}` therefore requires the same permission that should control access through the interface.

The developer also applies record-level authorization after loading the target user:

- Super Admin, Municipality Super Admin, Municipality IT Admin, and Municipality Executive can access records allowed by their municipal responsibilities.
- Municipality Sanitation Department access is limited to supported operational user types.
- Service-provider users can access users associated with the same service provider.
- Treatment-plant users can access users associated with the same treatment plant.
- A user may access their own record where applicable.
- All other cross-scope requests return HTTP `403`.

### B. Emptying endpoint permission enforcement

**File:** `app/Http/Controllers/Fsm/EmptyingController.php`

The developer adds explicit middleware for every sensitive Emptying action:

- `List Emptyings`: index and data listing
- `View Emptying`: record details
- `Add Emptying`: create and store
- `Edit Emptying`: edit and update
- `Delete Emptying`: delete
- `View Emptyings History`: history
- `Export Emptyings`: export

The `show` action now uses `findOrFail()`, performs record-scope authorization, and renders the record only after both checks pass.

Record access is allowed when the actor has approved municipality-level authority or when the record matches the actor's service provider, treatment plant, or creator identity. An unrelated numeric ID returns HTTP `403` instead of exposing the record.

### C. Guest-role correction

**File:** `database/seeders/RolePermissions/GuestSeeder.php`

The developer removes the `Emptyings` group from permissions assigned to the Guest role. The seeder also explicitly revokes any Emptying permissions from Guest, preventing stale permissions when the seeder is rerun.

### D. Existing-database correction

**File:** `database/migrations/2026_08_31_000001_revoke_guest_access_to_emptyings.php`

The migration:

1. Clears the Spatie permission cache.
2. Finds the existing `Guest` role.
3. Finds every permission in the `Emptyings` group.
4. Revokes each permission from Guest.
5. Clears the permission cache again.

The rollback is intentionally a no-op because restoring Guest access would recreate the security weakness.

## 4. Deployment Requirements

The developer or deployment owner must deploy the controller, seeder, and migration changes together. The migration can be run independently:

```bash
php artisan migrate --path=database/migrations/2026_08_31_000001_revoke_guest_access_to_emptyings.php
php artisan permission:cache-reset
php artisan optimize:clear
```

Confirm the migration status:

```bash
php artisan migrate:status --path=database/migrations/2026_08_31_000001_revoke_guest_access_to_emptyings.php
```

Expected result: the migration is marked **Ran**.

No database schema or endpoint contract changes are introduced. Users who depended on unintended Guest access will now receive `403` and must be assigned an approved role and permission.

## 5. Verification Required Before Approval

The senior reviewer should require evidence for the following cases in a test or staging environment:

| Test case | Expected result |
|---|---|
| Logged-out request to either endpoint | Redirect to login; no record data returned |
| Guest request to `/auth/users/{id}` | HTTP 403 |
| Guest request to `/fsm/emptying/{id}` | HTTP 403 |
| User missing the required View permission | HTTP 403 |
| Authorized user accesses an in-scope record | HTTP 200 and correct page |
| Authorized user changes ID to another organization's record | HTTP 403; no sensitive content |
| Request uses a nonexistent ID | HTTP 404 |
| Super Admin or approved municipal role accesses a valid record | HTTP 200 |

Confirm Guest has no Emptying permissions:

```php
$guest = Spatie\Permission\Models\Role::findByName('Guest');
$guest->permissions()->where('group', 'Emptyings')->pluck('name');
```

Expected result: an empty collection.

The existing automated suite passes, but it contains only two basic tests and does not provide sufficient security regression coverage. Feature tests should be added for unauthenticated, Guest, permitted same-scope, and denied cross-scope requests.

## 6. Residual Risk and Recommendation

This remediation addresses the two reported endpoint families. Other controllers using numeric IDs may contain the same permission-versus-record-scope gap. A focused review should identify all `show`, `edit`, `update`, `destroy`, `history`, export, and data endpoints and confirm that they enforce both action permission and object scope.

Recommended follow-up:

- Add automated authorization regression tests for the identified endpoints.
- Review other numeric-ID endpoints for IDOR patterns.
- Log and monitor repeated `403` responses and sequential-ID requests.
- Prefer route model binding and reusable policies for consistent record authorization.
- Retest role assignments after every permission-seeder change.

## 7. Approval Criteria

The change is ready for approval when:

- The security migration is marked as run.
- Guest has zero permissions in the Emptyings group.
- All denied requests return `403` or `404` without record content.
- Authorized same-scope workflows continue to work.
- Cross-service-provider and cross-treatment-plant ID changes are blocked.
- No unexpected HTTP `500` errors appear in `storage/logs/laravel.log`.
- Evidence from the verification matrix is attached to the review or release record.

**Conclusion:** The changes close the reported authorization gap by combining authentication, explicit permissions, and record-level scope validation. Production protection depends on deploying the code and running the permission-revocation migration and cache-clear commands.

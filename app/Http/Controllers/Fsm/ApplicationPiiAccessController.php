<?php

namespace App\Http\Controllers\Fsm;

use App\Http\Controllers\Controller;
use App\Models\BuildingInfo\Building;
use App\Services\ApplicationPiiAccessService;
use App\Services\OwnerPiiAuditService;
use App\Services\OwnerPiiPresenter;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ApplicationPiiAccessController extends Controller
{
    private ApplicationPiiAccessService $access;
    private OwnerPiiAuditService $audit;
    private OwnerPiiPresenter $ownerPiiPresenter;

    public function __construct(
        ApplicationPiiAccessService $access,
        OwnerPiiAuditService $audit,
        OwnerPiiPresenter $ownerPiiPresenter
    ) {
        $this->middleware('auth');
        $this->access = $access;
        $this->audit = $audit;
        $this->ownerPiiPresenter = $ownerPiiPresenter;
    }

    public function revealForCreate(Request $request)
    {
        $user = $request->user();
        $allowed = $user
            && $user->can('Add Application')
            && $this->access->canUnlock($user);

        abort_unless($allowed, 403);

        $validated = $request->validate([
            'bin' => ['required', 'string', 'max:100'],
            'current_password' => ['required', 'string', 'max:255'],
        ]);

        if (empty($user->password)
            || !Hash::check((string) $validated['current_password'], (string) $user->password)) {
            $this->audit->record(
                'application_owner_pii_lookup_password_failed',
                false,
                $user,
                null,
                [
                    'surface' => 'application_create',
                    'bin' => (string) $validated['bin'],
                ],
                'password_reentry'
            );

            return response()->json([
                'message' => __('Password confirmation failed.'),
                'errors' => [
                    'current_password' => [__('Password confirmation failed.')],
                ],
            ], 422);
        }

        $building = Building::query()
            ->where('bin', (string) $validated['bin'])
            ->first();
        $owner = $building ? $building->owners : null;

        if (!$building || !$owner) {
            $this->audit->record(
                'application_owner_pii_lookup_not_found',
                false,
                $user,
                null,
                [
                    'surface' => 'application_create',
                    'bin' => (string) $validated['bin'],
                ],
                'password_reentry'
            );

            return response()->json([
                'message' => __('Owner information was not found for the selected BIN.'),
            ], 404);
        }

        // Only this selected owner is decrypted, and only after step-up auth.
        $ownerPii = $this->ownerPiiPresenter->presentPlaintext($owner);

        $this->audit->record(
            'application_owner_pii_lookup_revealed',
            true,
            $user,
            null,
            [
                'surface' => 'application_create',
                'bin' => (string) $validated['bin'],
            ],
            'password_reentry'
        );

        return response()->json([
            'customer_name' => $ownerPii['owner_name'],
            'customer_gender' => $ownerPii['owner_gender'],
            'customer_contact' => $ownerPii['owner_contact'],
        ])->withHeaders([
            'Cache-Control' => 'no-store, private, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function unlockList(Request $request)
    {
        $user = $request->user();
        $allowed = $this->access->canUnlock($user)
            && $user->can('List Applications');

        abort_unless($allowed, 403);
        $request->validate([
            'current_password' => ['required', 'string', 'max:255'],
        ]);

        if (!Hash::check(
            (string) $request->input('current_password'),
            (string) $user->password
        )) {
            $this->access->lock();
            $this->audit->record(
                'application_pii_password_failed',
                false,
                $user,
                null,
                ['surface' => 'application_data_table'],
                'password_reentry'
            );

            return back()->withErrors([
                'application_pii_password' => __('Password confirmation failed.'),
            ]);
        }

        // List unlocks do not authorize create-form BIN lookups. Each create
        // lookup has its own password-confirmed, record-scoped request.
        $scopes = ['list', 'view'];

        if ($user->can('Edit Application')) {
            $scopes[] = 'edit';
        }

        $this->access->unlock(
            $user,
            'password_reentry',
            $scopes,
            ApplicationPiiAccessService::ALL_APPLICATIONS_RESOURCE_ID
        );
        $request->session()->regenerate();
        $this->audit->record(
            'application_pii_bulk_reveal_granted',
            true,
            $user,
            null,
            [
                'surface' => 'application_data_table',
                'expires_in_minutes' => (int) config('pii.unlock_minutes', 5),
            ],
            'password_reentry'
        );

        return redirect()->route('application.index', [], 303)->with(
            'success',
            __('Application customer PII is revealed for five minutes.')
        );
    }

    public function lock(Request $request)
    {
        $this->access->lock();
        $request->session()->regenerate();
        $this->audit->record(
            'application_pii_locked',
            true,
            $request->user(),
            null,
            ['surface' => 'application_module'],
            'session'
        );

        return back()->with('success', __('Application customer PII is locked.'));
    }
}

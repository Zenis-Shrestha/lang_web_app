<?php

namespace App\Http\Controllers\Fsm;

use App\Http\Controllers\Controller;
use App\Services\ApplicationCustomerPiiExportService;
use App\Services\ApplicationPiiAccessService;
use App\Services\OwnerPiiAuditService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Throwable;

class ApplicationCustomerPiiExportController extends Controller
{
    private ApplicationCustomerPiiExportService $exporter;
    private OwnerPiiAuditService $audit;
    private ApplicationPiiAccessService $piiAccess;

    public function __construct(
        ApplicationCustomerPiiExportService $exporter,
        OwnerPiiAuditService $audit,
        ApplicationPiiAccessService $piiAccess
    ) {
        $this->middleware('auth');
        $this->exporter = $exporter;
        $this->audit = $audit;
        $this->piiAccess = $piiAccess;
    }

    public function export(Request $request)
    {
        $this->authorizeExport($request);
        $maximumFileSize = max(1, (int) config('pii.export.max_file_kb', 2048));
        $request->validate([
            'application_id_csv' => ['required', 'string', 'max:' . ($maximumFileSize * 1024)],
            'application_export_password' => ['required', 'string', 'max:255'],
        ], [
            'application_id_csv.required' => __('Select a valid Application ID list CSV.'),
            'application_id_csv.max' => __('The Application ID list CSV is too large.'),
        ]);

        $user = $request->user();
        $password = (string) $request->input('application_export_password', '');

        if ($password === ''
            || empty($user->password)
            || !Hash::check($password, (string) $user->password)) {
            $this->piiAccess->lock();
            $this->audit->record(
                'application_pii_export_password_failed',
                false,
                $user,
                null,
                ['surface' => 'application_customer_pii_export'],
                'password_reentry'
            );

            return response()->json([
                'message' => __('Password confirmation failed.'),
                'errors' => [
                    'application_export_password' => [__('Password confirmation failed.')],
                ],
            ], 422);
        }

        try {
            $ids = $this->exporter->parseCsvText(
                (string) $request->input('application_id_csv')
            );
            $result = $this->exporter->rowsForApplicationIds($ids, $user);
        } catch (ValidationException $exception) {
            $this->audit->record(
                'application_pii_export_validation_failed',
                false,
                $user,
                null,
                ['surface' => 'application_customer_pii_export'],
                'password_reentry'
            );

            throw $exception;
        } catch (Throwable $exception) {
            $this->audit->record(
                'application_pii_export_failed',
                false,
                $user,
                null,
                ['surface' => 'application_customer_pii_export'],
                'password_reentry'
            );
            report($exception);

            return response()->json([
                'message' => __('The Application customer PII export could not be generated.'),
            ], 500);
        }

        $this->audit->record(
            'application_customer_pii_exported',
            true,
            $user,
            null,
            [
                'surface' => 'application_customer_pii_export',
                'requested_count' => $result['requested_count'],
                'exported_count' => $result['exported_count'],
                'missing_count' => $result['missing_count'],
            ],
            'password_reentry'
        );

        $headers = $this->exporter->headers();
        $rows = $result['rows'];

        return response()->streamDownload(
            function () use ($headers, $rows) {
                $output = fopen('php://output', 'wb');
                fwrite($output, "\xEF\xBB\xBF");
                fputcsv($output, $headers);

                foreach ($rows as $row) {
                    fputcsv($output, $row);
                }

                fclose($output);
            },
            'application-customer-pii-export-' . now()->format('Ymd-His') . '.csv',
            [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Cache-Control' => 'no-store, private, max-age=0',
                'Pragma' => 'no-cache',
                'Expires' => '0',
                'X-Content-Type-Options' => 'nosniff',
            ]
        );
    }

    private function authorizeExport(Request $request): void
    {
        $user = $request->user();
        $allowed = $user
            && $user->can('List Applications')
            && $user->can('View Application Customer PII')
            && $user->can('Unlock Application Customer PII')
            && $user->can('Export Application Customer PII');

        if (!$allowed) {
            $this->audit->record(
                'application_pii_export_permission_denied',
                false,
                $user,
                null,
                ['surface' => 'application_customer_pii_export'],
                'authenticated_session'
            );
        }

        abort_unless($allowed, 403);
    }
}

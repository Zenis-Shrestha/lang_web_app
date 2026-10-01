<?php
// Last Modified Date: 18-04-2024
// Developed By: Innovative Solution Pvt. Ltd. (ISPL)  
namespace App\Http\Controllers\Fsm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fsm\ApplicationRequest;
use App\Models\BuildingInfo\Building;
use App\Models\Fsm\Application;
use App\Models\Fsm\ServiceProvider;
use App\Services\Fsm\ApplicationService;
use App\Services\ApplicationPiiAccessService;
use App\Services\OwnerPiiAuditService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Venturecraft\Revisionable\Revision;
use Yajra\DataTables\Facades\DataTables;
use DB;
class ApplicationController extends Controller
{
    protected ApplicationService $applicationService;
    protected ApplicationPiiAccessService $applicationPiiAccess;
    protected OwnerPiiAuditService $piiAudit;

    public function __construct(
        ApplicationService $applicationService,
        ApplicationPiiAccessService $applicationPiiAccess,
        OwnerPiiAuditService $piiAudit
    ) {
        $this->applicationService = $applicationService;
        $this->applicationPiiAccess = $applicationPiiAccess;
        $this->piiAudit = $piiAudit;
        $this->middleware('pii.no-cache')->only([
            'getData',
            'buildingDetails',
            'show',
            'edit',
        ]);
    }

    /**
     * Display a list of applications.
     *
     * @return View
     */
    public function index()
    {
        $createBtnLink = Auth::user()->can('Add Application')?$this->applicationService->getCreateRoute():null;
        $createBtnTitle = __('Add Application') ;
        $exportBtnLink = Auth::user()->can('Export Applications')?$this->applicationService->getExportRoute():null;
        $reportBtnLink = Auth::user()->can('Generate Application Report')?$this->applicationService->getReportRoute():null;
        $filterFormFields = $this->applicationService->getFilterFormFields();
        $application_months = DB::select("select distinct extract(month from application_date) as date1 from fsm.applications where deleted_at is null order by date1 asc");
        $application_years = DB::select("select distinct extract(year from application_date) as date1 from fsm.applications where deleted_at is null order by date1 desc");
        $customerPiiListUnlocked = $this->customerPiiListIsUnlocked();
        $canUnlockCustomerPii = $this->applicationPiiAccess->canUnlock(Auth::user());
        $canExportCustomerPii = Auth::user()->can('List Applications')
            && Auth::user()->can('View Application Customer PII')
            && Auth::user()->can('Unlock Application Customer PII')
            && Auth::user()->can('Export Application Customer PII');
        $customerPiiUnlockSeconds = $customerPiiListUnlocked
            ? $this->applicationPiiAccess->secondsRemaining(
                Auth::user(),
                'list',
                ApplicationPiiAccessService::ALL_APPLICATIONS_RESOURCE_ID
            )
            : 0;

        return view('fsm.applications.index', compact(
            'createBtnLink',
            'createBtnTitle',
            'filterFormFields',
            'exportBtnLink',
            'reportBtnLink',
            'application_months',
            'application_years',
            'customerPiiListUnlocked',
            'canUnlockCustomerPii',
            'canExportCustomerPii',
            'customerPiiUnlockSeconds'
        ));
    }

    /**
     * Prepare data for the DataTable.
     *
     * @param Request $request
     * @return DataTables
     * @throws Exception
     */
    public function getData(Request $request)
    {
        $revealed = $this->customerPiiListIsUnlocked();

        if ($revealed) {
            $this->piiAudit->record(
                'application_customer_pii_bulk_viewed',
                true,
                Auth::user(),
                null,
                [
                    'surface' => 'application_data_table',
                    'page_start' => (int) $request->input('start', 0),
                    'page_length' => (int) $request->input('length', 10),
                    'customer_name_filter_used' => $request->filled('customer_name'),
                ],
                'password_reentry'
            );
        }

        return $this->applicationService->getDatatable($request, $revealed);
    }

    /**
     * Display the create form for application.
     *
     * @return View
     */
    public function create(Request $request)
    {
        $action_type = $request->query('action_type')
            ?? old('action_type');

        if ($action_type !== 'confirm') {
            session()->forget([
                'schedule_accept',
                'action_type',
                'bin',
                'containment_id',
                'road_code',
                'ward',
                'service_provider_id',
                'next_emptying_date',
            ]);
        }

        $user = Auth::user();
        $canRevealOwnerPii = $user
            && $user->can('Add Application')
            && $this->applicationPiiAccess->canUnlock($user);

        return view('fsm.applications.create',[
            'formAction' => $this->applicationService->getCreateFormAction(),
            'formFields' => $this->applicationService->getCreateFormFields(),
            'indexAction' => $this->applicationService->getIndexAction(),
            'canRevealOwnerPii' => $canRevealOwnerPii,
            'action_type' => $action_type,
        ]);
    }

    /**
     * Get the building details for the selected address.
     *
     * @param Request $request
     * @return JsonResponse
     * @throws Exception
     */
    public function buildingDetails(Request $request)
    {
        // Address selection must never reveal owner PII implicitly. The user
        // must use the record-scoped, password-confirmed reveal endpoint.
        return $this->applicationService->getBuildingDetails($request, false);
    }

    /**
     * Store a newly created application in storage.
     *
     * @param ApplicationRequest $request
     * @return RedirectResponse|Redirector
     */
    public function store(ApplicationRequest $request)
    {
        return $this->applicationService->createApplication($request);
    }

    /**
     * Display the specified application.
     *
     * @param  int  $id
     * @return View
     */
    public function show($id)
    {
        $application = Application::find($id);
    
        if ($application) {
            $page_title =__('Application Details') ;
            $customerPiiUnlocked = $this->customerPiiIsUnlocked($application, 'view');
            $formFields = $this->applicationService->getShowFormFields(
                $application,
                $customerPiiUnlocked
            );
            if ($customerPiiUnlocked) {
                $this->piiAudit->record(
                    'application_customer_pii_viewed',
                    true,
                    Auth::user(),
                    null,
                    ['application_id' => (int) $application->id, 'surface' => 'application_details'],
                    'password_reentry'
                );
            }
            $indexAction = $this->applicationService->getIndexAction();

            return view('layouts.show', compact('page_title', 'formFields', 'application', 'indexAction'))
                ->with('cardForm', true);
        } else {
            abort(404);
        }
    }
    
    /**
     * Show the form for editing the specified application.
     *
     * @param  int  $id
     * @return View
     */
    public function edit($id)
    {
        $application = Application::find($id);
        if ($application) {
            $page_title =__("Edit Application");
            $customerPiiUnlocked = $this->customerPiiIsUnlocked($application, 'edit');
            $formFields = $this->applicationService->getEditFormFields(
                $application,
                $customerPiiUnlocked
            );
            if ($customerPiiUnlocked) {
                $this->piiAudit->record(
                    'application_customer_pii_viewed',
                    true,
                    Auth::user(),
                    null,
                    ['application_id' => (int) $application->id, 'surface' => 'application_edit'],
                    'password_reentry'
                );
            }
            $formAction = $this->applicationService->getEditFormAction($application);
            $indexAction = $this->applicationService->getIndexAction();
            return view('fsm.applications.edit',compact('page_title','formFields','formAction','indexAction','application'),['cardForm'=>true]);
        } else {
            abort(404);
        }
    }

    /**
     * Update the specified application in storage.
     *
     * @param ApplicationRequest $request
     * @param int $id
     * @return Redirector|RedirectResponse
     */
    public function update(ApplicationRequest $request, $id)
    {
        $application = Application::findOrFail($id);
        $containsCustomerPii = collect([
            'customer_name',
            'customer_gender',
            'customer_contact',
        ])->contains(function ($field) use ($request) {
            return $request->filled($field);
        });

        // Browser-disabled fields are not a security boundary. A crafted
        // request that changes customer PII must also hold the edit grant.
        if ($containsCustomerPii
            && !$this->customerPiiIsUnlocked($application, 'edit')) {
            $this->piiAudit->record(
                'application_customer_pii_update_denied',
                false,
                Auth::user(),
                null,
                ['application_id' => (int) $application->id],
                'session'
            );
            abort(403, __('Application customer PII must be unlocked before it can be changed.'));
        }

        if ($containsCustomerPii) {
            $this->piiAudit->record(
                'application_customer_pii_update_authorized',
                true,
                Auth::user(),
                null,
                ['application_id' => (int) $application->id],
                'password_reentry'
            );
        }

        return $this->applicationService->updateApplication($request,$id);
    }

    private function customerPiiListIsUnlocked(): bool
    {
        $user = Auth::user();

        return $user && $this->applicationPiiAccess->isUnlocked(
            $user,
            'list',
            ApplicationPiiAccessService::ALL_APPLICATIONS_RESOURCE_ID
        );
    }

    private function customerPiiIsUnlocked(
        Application $application,
        string $scope
    ): bool {
        $user = Auth::user();

        return $user
            && $this->applicationPiiAccess->isUnlocked(
                $user,
                $scope,
                (string) $application->id
            );
    }

    /**
     * Remove the specified application from storage.
     *
     * @param  int  $id
     * @return Redirector|RedirectResponse
     */
    public function destroy($id)
    {
        try {
            $application = Application::findOrFail($id);
            if($application->emptying()->exists()){
                return redirect('fsm/application')->with('error',__('Cannot delete Application that has associated Emptying Information.'));
            }
            if($application->sludge_collection()->exists()){
                return redirect('fsm/application')->with('error',__('Cannot delete Application that has associated Sludge Collection Information.'));
            }
            if($application->feedback()->exists()){
                return redirect('fsm/application')->with('error',__('Cannot delete Application that has associated Feedback Information.'));
            }
            $application->delete();
        } catch (\Throwable $e) {
            return redirect('fsm/application')->with('error',__('Failed to delete Application.'));
        }
        return redirect('fsm/application')->with('success',__('Application deleted successfully.'));

    }

    /**
     * Get the history of changes on the specified application.
     *
     * @param  int  $id
     * @return Redirector|RedirectResponse
     */
    public function history($id)
    {
       return $this->applicationService->getApplicationHistory($id);
    }

    /**
     * Export applications to csv.
     *
     * @return Redirector|RedirectResponse
     */

    public function export(Request $request)
    {
      try {
        $this->applicationService->export($request);
        } catch (\Throwable $e) {
            return redirect(route('application.index'))->with('error',__('Failed to export applications.'));
        }
    }
    /**
    * Generate a PDF report for monthly applications.
    *
    * @param int $year The year for the report.
    * @param int $month The month for the report.
    * @return \Illuminate\Http\Response The generated PDF report.
    */
    public function monthlyApplicationsPdf($year, $month)
    {
        return $this->applicationService->fethMonthlyReport($year, $month);
    }
    /**
    * Retrieve a report for a specific application.
    *
    * @param int $id The ID of the application.
    * @return \Illuminate\Http\Response The application report.
    */
    public function applicationReport($id)
    {
        return $this->applicationService->getApplicationReport($id);
    }
    public function getServiceProvider($service_provider_id = null)
    {
        if ($service_provider_id) {
            // Fetch the specific service provider's data
            return ServiceProvider::Operational()
                ->where('id', $service_provider_id)
                ->pluck('company_name', 'id')
                ->toArray();
        } else {
            // Fetch all operational service providers
            return ServiceProvider::Operational()
                ->pluck('company_name', 'id')
                ->toArray();
        }
    }
    
    
    
}

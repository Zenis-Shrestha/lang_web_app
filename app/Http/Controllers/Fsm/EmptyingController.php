<?php
// Last Modified Date: 18-04-2024
// Developed By: Innovative Solution Pvt. Ltd. (ISPL)  
namespace App\Http\Controllers\Fsm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Fsm\EmptyingApiRequest;
use App\Http\Requests\Fsm\EmptyingRequest;
use App\Models\Fsm\Application;
use App\Models\Fsm\Emptying;
use App\Services\Fsm\EmptyingService;
use DateTimeZone;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Redirector;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Intervention\Image\Facades\Image;
use Yajra\DataTables\Facades\DataTables;
use App\Models\Fsm\Containment;
use DB;

class EmptyingController extends Controller
{
    protected EmptyingService $emptyingService;

    public function __construct(EmptyingService $emptyingService)
    {
        $this->middleware('auth');
        $this->middleware('permission:List Emptyings', ['only' => ['index', 'getData']]);
        $this->middleware('permission:View Emptying', ['only' => ['show']]);
        $this->middleware('permission:Add Emptying', ['only' => ['create', 'store']]);
        $this->middleware('permission:Edit Emptying', ['only' => ['edit', 'update']]);
        $this->middleware('permission:Delete Emptying', ['only' => ['destroy']]);
        $this->middleware('permission:View Emptyings History', ['only' => ['history']]);
        $this->middleware('permission:Export Emptyings', ['only' => ['export']]);
        $this->emptyingService = $emptyingService;
    }

    /**
     * Display a list of emptying history.
     *
     * @return View
     */
    public function index(Request $request)
    {
        $filterFormFields = $this->emptyingService->getFilterFormFields();
        $containment_code = '';
        if ($request->filled('containment_public_id')) {
            $containment_code = Containment::where('public_id', $request->containment_public_id)
                ->firstOrFail()
                ->id;
        } elseif ($request->filled('containment_code')) {
            // Temporary compatibility for existing bookmarks.
            $containment_code = $request->containment_code;
        }
        $exportBtnLink = Auth::user()->can('Export Emptyings')?$this->emptyingService->getExportRoute():null;
        return view('fsm.emptying.index',compact('filterFormFields','exportBtnLink','containment_code'));
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
        return $this->emptyingService->getDatatable($request);
    }

    /**
     * Display the create form for emptying.
     *
     * @return View
     */
    public function create(int $id)
    {
        return view('fsm.emptying.create',[
            'formAction' => $this->emptyingService->getCreateFormAction(),
            'formFields' => $this->emptyingService->getCreateFormFields($id),
            'indexAction' => url()->previous(),
            'application_id' => $id
        ]);
    }

    /**
     * Store a newly created emptying in storage.
     *
     * @param EmptyingRequest $request
     * @return RedirectResponse|Redirector
     */
    public function store(EmptyingRequest $request)
    {
        return $this->emptyingService->createEmptying($request);
    }

    /**
     * Display the specified emptying.
     *
     * @param  int  $id
     * @return View
     */
    public function show($id)
    {
        $emptying = Emptying::findOrFail($id);
        $this->authorizeEmptyingRecord($emptying);

        $page_title = __("Emptying Details");
        $formFields = $this->emptyingService->getShowFormFields($emptying);
        $indexAction = url()->previous();
        return view('layouts.show',compact('page_title','formFields','emptying','indexAction'));
    }

    /**
     * Scope direct record access to the actor's municipality-level authority,
     * service provider, treatment plant, or records created by that actor.
     */
    private function authorizeEmptyingRecord(Emptying $emptying): void
    {
        $actor = Auth::user();

        if ($actor->hasAnyRole([
            'Super Admin',
            'Municipality - Super Admin',
            'Municipality - IT Admin',
            'Municipality - Sanitation Department',
            'Municipality - Executive',
        ])) {
            return;
        }

        $authorized = $emptying->user_id === $actor->id;

        if ($actor->service_provider_id) {
            $authorized = $emptying->service_provider_id === $actor->service_provider_id;
        } elseif ($actor->treatment_plant_id) {
            $authorized = $emptying->treatment_plant_id === $actor->treatment_plant_id;
        }

        abort_unless($authorized, 403);
    }

    /**
     * Show the form for editing the specified emptying.
     *
     * @param  int  $id
     * @return View
     */
    public function edit($id)
    {
        $emptying = Emptying::find($id);
        if( !(Auth::user()->hasRole('Super Admin') || Auth::user()->hasRole('Municipality - Sanitation Department')) )
        {
            if($emptying->created_at->diffInDays(today()) > 1)
            {
                return redirect('fsm/application')->with('error',__('Cannot update Emptying Information 24 hours after creation. Please contact Sanitation Department for more information.'));
            }
        }
        if ($emptying) {
            $page_title = __("Edit Emptying Service Details");
            $formFields = $this->emptyingService->getEditFormFields($emptying);
            $formAction = $this->emptyingService->getEditFormAction($emptying);
            $indexAction = url()->previous();
            return view('fsm.emptying.edit',compact('page_title','formFields','formAction','indexAction','emptying'));
        } else {
            abort(404);
        }
    }

    /**
     * Update the specified emptying in storage.
     *
     * @param EmptyingRequest $request
     * @param int $id
     * @return Redirector|RedirectResponse
     */
    public function update(EmptyingRequest $request, $id)
    {
        return $this->emptyingService->updateEmptying($request,$id);
    }

    /**
     * Get the history of changes on the specified emptying
     *
     * @param  int  $id
     * @return Redirector|RedirectResponse
     */
    public function history($id)
    {
        return $this->emptyingService->getEmptyingHistory($id);
    }

    /**
     * Remove the specified emptying from storage.
     *
     * @param  int  $id
     * @return Redirector|RedirectResponse
     */
    public function destroy($id)
    {
        try {
            $emptying = Emptying::findOrFail($id);
            if($emptying->sludge_collection()->exists())
            {
                return redirect(route('emptying.index'))->with('error',__('Cannot delete Emptying that has assocaited Sludge Collection Information.'));
            }
            if($emptying->feedback()->exists())
            {
                return redirect(route('emptying.index'))->with('error',__('Cannot delete Emptying that has assocaited Feedback Information.'));
            }
            // allowing super admin and sanitation department to delete anytime, else only allowing deletion within 24 hours.
            if( !(Auth::user()->hasRole('Super Admin') || Auth::user()->hasRole('Municipality - Sanitation Department')) )
            {
                if($emptying->created_at->diffInDays(today()) > 1)
                {
                    return redirect(route('emptying.index'))->with('error',__('Cannot delete Emptying Information 24 hours after creation. Please contact Sanitation Department for more information.'));
                }
            }
            // updating emptying status for particular application 
            $application = Application::findOrFail($emptying->application_id);
            $application->emptying_status=false;
            $application->save();
            // deleting emptying record
            $emptying->delete();
            // updating containments emptied information
            $containment = Containment::findOrFail($application->containment_id);
            // decreasing times emptied by 1 
            if($containment->no_of_times_emptied > 0)
            {
                $containment->no_of_times_emptied = $containment->no_of_times_emptied - 1;
            }
            // if times emptied is zero, nullifying all values
            if ($containment->no_of_times_emptied == 0)
            {
                $containment->emptied_status = false;
                $containment->last_emptied_date = null;
                $containment->next_emptying_date = null;
            }
            // else, fetching old application data of previous emptying and updating the values
            else
            {
                $previous_application = Application::where('containment_id',$application->containment_id)
                ->where('id','!=',$application->id)
                ->where('emptying_status',true)
                ->whereNULL('deleted_at')
                ->orderBy('created_at', 'desc')->get();
                if($previous_application[0]->emptying()->exists())
                {
                    $previous_emptying = Emptying::where('application_id',$previous_application[0]->id)
                    ->where('id','!=',$application->id)
                    ->whereNULL('deleted_at')
                    ->orderBy('created_at', 'desc')->get();
                    if(!empty($previous_emptying))
                    {
                        $containment->emptied_status = true;
                        $containment->last_emptied_date = $previous_emptying[0]->emptied_date;
                        $containment->next_emptying_date = $previous_emptying[0]->emptied_date->addYears(3);
                    }
                }
                else
                {
                    $containment->last_emptied_date = null;
                    $containment->next_emptying_date = null;
                }
            }
            $containment->save();
            return redirect('fsm/emptying')->with('success',__('Emptying deleted successfully.'));
        } catch (\Exception $e) {
            return redirect('fsm/emptying')->with('error',__('Failed to delete Emptying.'));
        }
    }

    /**
     * Export applications to csv.
     *
     * @return Redirector|RedirectResponse
     */

    public function export(Request $request)
    {
        
     try {
            $this->emptyingService->export($request);
        } catch (\Throwable $e) {
            return redirect(route('emptying.index'))->with('error',__('Failed to export emptyings.'));
        }
    }
}

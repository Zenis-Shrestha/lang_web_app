<?php

namespace App\Http\Controllers\Fsm;

use App\Http\Controllers\Controller;
use App\Services\Fsm\DesludgingScheduleService;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class DesludgingScheduleController extends Controller
{
    protected $desludgingScheduleService;

    public function __construct(
        DesludgingScheduleService $desludgingScheduleService
    ) {
        $this->middleware('auth');

        $this->middleware(
            'permission:List Schedule Desludging',
            ['only' => ['index', 'getData']]
        );

        $this->middleware(
            'permission:Regenerate Schedule Desludging',
            ['only' => ['regenerateDesludgingSchedule']]
        );

        $this->middleware(
            'permission:Confirm Schedule Desludging',
            ['only' => ['accept']]
        );

        $this->middleware(
            'permission:Delete Schedule Desludging',
            ['only' => ['disagree']]
        );

        $this->desludgingScheduleService =
            $desludgingScheduleService;
    }

    public function index()
    {
        $page_title = __('Desludging Schedule');

        return view(
            'fsm.desludging-schedule.index',
            compact('page_title')
        );
    }

    public function getData(Request $request)
    {
        return $this->desludgingScheduleService->getData($request);
    }

    public function accept(Request $request)
    {
        return $this->desludgingScheduleService
            ->redirectToApplication($request);
    }

    public function disagree(string $bin)
    {
        return $this->desludgingScheduleService->disagree($bin);
    }

    public function regenerateDesludgingSchedule()
    {
        try {
            $result = $this->desludgingScheduleService->regenerate(
                (int) auth()->id()
            );

            return response()->json([
                'status' => 'success',
                'message' => __('The desludging schedule was generated successfully.'),
                'priority_updated_count' => $result['priority_updated_count'],
                'scheduled_count' => $result['scheduled_count'],
                'temporary_schedule_count' => $result['temporary_schedule_count'],
                'service_area_assigned_count' =>
                    $result['service_area_assigned_count'],
                'provider_assigned_count' =>
                    $result['provider_assigned_count'],
                'provider_unassigned_count' =>
                    $result['provider_unassigned_count'],
            ]);
        } catch (HttpExceptionInterface $exception) {
            return response()->json([
                'status' => 'error',
                'message' => $exception->getMessage(),
            ], $exception->getStatusCode());
        } catch (\Throwable $exception) {
            report($exception);

            return response()->json([
                'status' => 'error',
                'message' => __(
                    'The desludging schedule could not be generated.'
                ),
            ], 500);
        }
    }

}

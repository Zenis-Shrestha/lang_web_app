<?php

namespace Tests\Feature\Fsm;

use App\Services\Fsm\DesludgingScheduleService;
use Carbon\Carbon;
use ReflectionMethod;
use Tests\TestCase;

class DesludgingScheduleGenerationTest extends TestCase
{
    /** @test */
    public function null_dates_receive_priority_one(): void
    {
        $today = Carbon::create(2026, 8, 26)->startOfDay();

        $this->assertSame(1, $this->priority(null, null, $today));
    }

    /** @test */
    public function a_future_last_emptying_date_falls_back_to_construction_date(): void
    {
        $today = Carbon::create(2026, 8, 26)->startOfDay();
        $future = $this->normalize('2026-08-27', $today);
        $construction = $this->normalize('2024-08-26', $today);

        $this->assertNull($future);
        $this->assertSame(2, $this->priority($future, $construction, $today));
    }

    /** @test */
    public function exact_one_and_three_year_boundaries_are_preserved(): void
    {
        $today = Carbon::create(2026, 8, 26)->startOfDay();

        $this->assertSame(
            2,
            $this->priority($today->copy()->subYearsNoOverflow(3), null, $today)
        );
        $this->assertSame(
            3,
            $this->priority($today->copy()->subYearsNoOverflow(1), null, $today)
        );
    }

    /** @test */
    public function generation_source_contains_priority_and_date_safety_contracts(): void
    {
        $source = file_get_contents(
            app_path('Services/Fsm/DesludgingScheduleService.php')
        );

        $this->assertStringContainsString('setEmptyingDate', $source);
        $this->assertStringContainsString('refreshTemporarySchedule', $source);
        $this->assertStringContainsString('NULL::bigint', $source);
        $this->assertStringContainsString(
            'FROM fsm.applications AS application',
            $source
        );
        $this->assertStringContainsString(
            'ORDER BY fstp_distance ASC NULLS LAST',
            $source
        );
        $this->assertStringContainsString('next_emptying_date', $source);
        $this->assertStringContainsString('maximumDays', $source);
        $this->assertStringNotContainsString('ensureProviderWardsAreConfigured', $source);
        $this->assertStringContainsString('IS DISTINCT FROM', $source);
        $this->assertStringContainsString('pg_try_advisory_xact_lock', $source);
    }

    /** @test */
    public function page_always_cleans_up_schedule_loading_state(): void
    {
        $view = file_get_contents(
            resource_path('views/fsm/desludging-schedule/index.blade.php')
        );

        $this->assertStringContainsString(
            'scheduleTable.ajax.reload(null, false)',
            $view
        );
        $this->assertStringContainsString('allowOutsideClick: false', $view);
        $this->assertStringContainsString('allowEscapeKey: false', $view);
        $this->assertStringContainsString('timeout: 120000', $view);
        $this->assertStringContainsString('window.clearInterval', $view);
        $this->assertStringContainsString(".prop('disabled', false)", $view);
    }

    /** @test */
    public function uncovered_wards_are_randomly_but_evenly_distributed(): void
    {
        $method = new ReflectionMethod(
            DesludgingScheduleService::class,
            'buildBalancedRandomPlan'
        );
        $method->setAccessible(true);

        $plan = $method->invoke(
            new DesludgingScheduleService(),
            collect([10, 20, 30]),
            collect(range(1, 8))
        );

        $this->assertEqualsCanonicalizing(
            [10, 20, 30],
            array_keys($plan)
        );

        $assignedWards = collect($plan)->flatten()->all();
        $this->assertEqualsCanonicalizing(range(1, 8), $assignedWards);
        $this->assertCount(8, array_unique($assignedWards));

        $wardCounts = array_map('count', $plan);
        $this->assertLessThanOrEqual(
            1,
            max($wardCounts) - min($wardCounts)
        );
    }

    /** @test */
    public function provider_assignment_is_hidden_inside_regeneration(): void
    {
        $routes = file_get_contents(base_path('routes/web.php'));
        $scheduleController = file_get_contents(
            app_path('Http/Controllers/Fsm/DesludgingScheduleController.php')
        );
        $service = file_get_contents(
            app_path('Services/Fsm/DesludgingScheduleService.php')
        );
        $scheduleView = file_get_contents(
            resource_path('views/fsm/desludging-schedule/index.blade.php')
        );
        $permissions = file_get_contents(
            database_path('seeders/PermissionsSeeder.php')
        );

        $this->assertStringNotContainsString(
            'service-provider-assignment/preview',
            $routes
        );
        $this->assertStringNotContainsString(
            'permission:Assign Service Provider',
            $scheduleController
        );
        $this->assertStringNotContainsString(
            'id="assign-service-provider"',
            $scheduleView
        );
        $this->assertStringNotContainsString(
            "'name' => 'Assign Service Provider'",
            $permissions
        );
        $this->assertStringContainsString(
            "'provider_assigned_count'",
            $scheduleController
        );
        $this->assertStringContainsString(
            'assignUncoveredWardsToEmptyProviders',
            $service
        );
        $this->assertStringContainsString(
            'assignGeneratedScheduleProvidersByBinWard',
            $service
        );
        $this->assertStringContainsString(
            "BTRIM(service_area) = ''",
            $service
        );
        $refreshPosition = strpos(
            $service,
            '$this->refreshTemporarySchedule'
        );
        $coveragePosition = strpos(
            $service,
            '$this->assignUncoveredWardsToEmptyProviders'
        );
        $providerPosition = strpos(
            $service,
            '$this->assignGeneratedScheduleProvidersByBinWard'
        );

        $this->assertNotFalse($refreshPosition);
        $this->assertGreaterThan($refreshPosition, $coveragePosition);
        $this->assertGreaterThan($coveragePosition, $providerPosition);
    }

    /** @test */
    public function confirm_application_context_persists_and_locks_address_fields(): void
    {
        $service = file_get_contents(
            app_path('Services/Fsm/DesludgingScheduleService.php')
        );
        $request = file_get_contents(
            app_path('Http/Requests/Fsm/ApplicationRequest.php')
        );
        $view = file_get_contents(
            resource_path('views/fsm/applications/create.blade.php')
        );

        $this->assertStringContainsString(
            "session()->put('schedule_accept', \$data)",
            $service
        );
        $this->assertStringNotContainsString(
            "session()->flash('schedule_accept'",
            $service
        );
        $this->assertStringContainsString(
            'protected function prepareForValidation()',
            $request
        );
        $this->assertStringContainsString(
            'function lockConfirmAddressFields()',
            $view
        );

        foreach (['road_code', 'bin', 'containment_id', 'ward'] as $field) {
            $this->assertStringContainsString("'{$field}'", $view);
        }

        $this->assertStringContainsString(
            ".prop('disabled', true)",
            $view
        );
        $this->assertStringContainsString(
            "'data-confirm-address': fieldName",
            $view
        );

        $applicationService = file_get_contents(
            app_path('Services/Fsm/ApplicationService.php')
        );

        $this->assertStringContainsString(
            "'copyDetails' => true",
            $applicationService
        );
        $this->assertStringContainsString("value === 'null'", $view);
        $this->assertStringContainsString("value === 'undefined'", $view);
    }

    private function normalize($value, Carbon $today): ?Carbon
    {
        $method = new ReflectionMethod(
            DesludgingScheduleService::class,
            'normalizeDate'
        );
        $method->setAccessible(true);

        return $method->invoke(new DesludgingScheduleService(), $value, $today);
    }

    private function priority(
        ?Carbon $lastEmptied,
        ?Carbon $construction,
        Carbon $today
    ): int {
        $method = new ReflectionMethod(
            DesludgingScheduleService::class,
            'calculatePriority'
        );
        $method->setAccessible(true);

        return $method->invoke(
            new DesludgingScheduleService(),
            $lastEmptied,
            $construction,
            $today
        );
    }
}

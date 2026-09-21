<?php

namespace Tests\Feature\Fsm;

use App\Services\Fsm\DesludgingScheduleService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery;
use Tests\Feature\Fsm\Concerns\CreatesDesludgingTestData;
use Tests\TestCase;

class DesludgingRegenerationFeatureTest extends TestCase
{
    use CreatesDesludgingTestData;
    use DatabaseTransactions;

    /** @test */
    public function regeneration_returns_the_generated_schedule_counts(): void
    {
        $user = $this->createDesludgingUser();
        $result = [
            'priority_updated_count' => 3,
            'scheduled_count' => 4,
            'temporary_schedule_count' => 4,
            'service_area_assigned_count' => 2,
            'provider_assigned_count' => 3,
            'provider_unassigned_count' => 1,
        ];

        $service = Mockery::mock(DesludgingScheduleService::class);
        $service->shouldReceive('regenerate')
            ->once()
            ->with($user->id)
            ->andReturn($result);
        $this->app->instance(DesludgingScheduleService::class, $service);

        $response = $this->actingAs($user)
            ->withoutMiddleware()
            ->postJson('/fsm/desludging-schedule/regenerate');

        $response->assertOk()->assertExactJson(array_merge([
            'status' => 'success',
            'message' => 'The desludging schedule was generated successfully.',
        ], $result));
    }
}

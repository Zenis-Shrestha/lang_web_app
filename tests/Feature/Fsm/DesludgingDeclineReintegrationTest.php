<?php

namespace Tests\Feature\Fsm;

use App\Models\Fsm\ServiceProvider;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Spatie\Permission\Models\Role;
use Tests\Feature\Fsm\Concerns\CreatesDesludgingTestData;
use Tests\TestCase;

class DesludgingDeclineReintegrationTest extends TestCase
{
    use CreatesDesludgingTestData;
    use DatabaseTransactions;

    /** @test */
    public function declining_moves_the_property_to_the_reintegration_list(): void
    {
        $property = $this->createScheduledProperty();

        $response = $this->withoutMiddleware()->postJson(
            '/fsm/desludging-schedule/' . $property['bin'] . '/disagree'
        );

        $response
            ->assertOk()
            ->assertJson(['status' => 'success']);
        $this->assertDatabaseHas('fsm.containments', [
            'id' => $property['containmentId'],
            'status' => 4,
        ]);
        $this->assertDatabaseMissing('fsm.desludging_schedule_temp', [
            'containment_id' => $property['containmentId'],
        ]);

        $user = $this->createDesludgingUser();
        $role = Role::firstOrCreate([
            'name' => 'Super Admin',
            'guard_name' => 'web',
        ]);
        $user->assignRole($role);

        $listResponse = $this->actingAs($user)
            ->withoutMiddleware()
            ->getJson('/fsm/desludging-reintegration/data?' . http_build_query([
                'draw' => 1,
                'start' => 0,
                'length' => 10,
                'bin' => $property['bin'],
            ]));

        $listResponse
            ->assertOk()
            ->assertJsonFragment(['bin' => $property['bin']]);
    }

    /** @test */
    public function confirming_reintegration_selects_the_covering_provider(): void
    {
        // Keep provider selection deterministic despite pre-existing rows.
        ServiceProvider::query()->update(['status' => 0]);
        $provider = $this->createDesludgingProvider(7);
        $property = $this->createScheduledProperty($provider, 4);
        $user = $this->createDesludgingUser();

        $this->assertDatabaseMissing('fsm.applications', [
            'containment_id' => $property['containmentId'],
        ]);

        $response = $this->actingAs($user)
            ->withoutMiddleware()
            ->post('/fsm/desludging-reintegration/confirm', [
                'bin' => $property['bin'],
                'containment_id' => $property['containmentId'],
                'road_code' => $property['roadCode'],
            ]);

        $response
            ->assertRedirect(route('application.create', [
                'action_type' => 'confirm',
            ]))
            ->assertSessionHas('schedule_accept', function ($data) use (
                $property,
                $provider
            ) {
                return $data['bin'] === $property['bin']
                    && $data['containment_id'] === $property['containmentId']
                    && (int) $data['service_provider_id'] === $provider->id;
            });
    }
}

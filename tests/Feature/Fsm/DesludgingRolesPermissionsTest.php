<?php

namespace Tests\Feature\Fsm;

use App\Services\Fsm\DesludgingScheduleService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Mockery;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Illuminate\Support\Str;
use Tests\Feature\Fsm\Concerns\CreatesDesludgingTestData;
use Tests\TestCase;

class DesludgingRolesPermissionsTest extends TestCase
{
    use CreatesDesludgingTestData;
    use DatabaseTransactions;

    /**
     * @test
     * @dataProvider protectedActionProvider
     */
    public function protected_actions_enforce_their_permissions(
        string $uri,
        string $permissionName,
        string $serviceMethod
    ): void {
        $service = Mockery::mock(DesludgingScheduleService::class);
        if ($serviceMethod === 'regenerate') {
            $service->shouldReceive('regenerate')->once()->andReturn([
                'priority_updated_count' => 0,
                'scheduled_count' => 0,
                'temporary_schedule_count' => 0,
                'service_area_assigned_count' => 0,
                'provider_assigned_count' => 0,
                'provider_unassigned_count' => 0,
            ]);
        } else {
            $service->shouldReceive($serviceMethod)
                ->once()
                ->andReturn(response()->json(['status' => 'success']));
        }
        $this->app->instance(DesludgingScheduleService::class, $service);

        $unauthorizedUser = $this->createDesludgingUser();

        $this->actingAs($unauthorizedUser)
            ->post($uri)
            ->assertForbidden();

        $authorizedUser = $this->createDesludgingUser();
        $permission = Permission::firstOrCreate([
            'name' => $permissionName,
            'guard_name' => 'web',
        ]);
        $role = Role::create([
            'name' => 'PHPUnit ' . $permissionName . ' Role',
            'guard_name' => 'web',
        ]);
        $role->givePermissionTo($permission);
        $authorizedUser->assignRole($role);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($authorizedUser)
            ->post($uri)
            ->assertSuccessful();
    }

    public function protectedActionProvider(): array
    {
        return [
            'regenerate schedule' => [
                '/fsm/desludging-schedule/regenerate',
                'Regenerate Schedule Desludging',
                'regenerate',
            ],
            'accept schedule' => [
                '/fsm/desludging-schedule/accept',
                'Confirm Schedule Desludging',
                'redirectToApplication',
            ],
            'decline schedule' => [
                '/fsm/desludging-schedule/TEST-BIN/disagree',
                'Delete Schedule Desludging',
                'disagree',
            ],
            'confirm reintegration' => [
                '/fsm/desludging-reintegration/confirm',
                'Confirm Schedule Reintegration',
                'redirectReintegrationToApplication',
            ],
        ];
    }

    /**
     * @test
     * @dataProvider listingPageProvider
     */
    public function listing_pages_require_login_and_list_permission(
        string $uri,
        string $permissionName
    ): void {
        $this->get($uri)->assertRedirect('/login');

        $unauthorizedUser = $this->createDesludgingUser();
        $this->actingAs($unauthorizedUser)
            ->get($uri)
            ->assertForbidden();

        $authorizedUser = $this->createDesludgingUser();
        $permission = Permission::firstOrCreate([
            'name' => $permissionName,
            'guard_name' => 'web',
        ]);
        $role = Role::create([
            'name' => 'PHPUnit List Role ' . Str::random(12),
            'guard_name' => 'web',
        ]);
        $role->givePermissionTo($permission);
        $authorizedUser->assignRole($role);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $this->actingAs($authorizedUser)
            ->get($uri)
            ->assertOk();
    }

    public function listingPageProvider(): array
    {
        return [
            'schedule list' => [
                '/fsm/desludging-schedule',
                'List Schedule Desludging',
            ],
            'reintegration list' => [
                '/fsm/desludging-reintegration',
                'List Schedule Reintegration',
            ],
        ];
    }
}

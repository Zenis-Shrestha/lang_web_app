<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\ApplicationPiiAccessService;
use App\Services\PiiAccessService;
use App\Services\PropertyTaxPiiAccessService;
use Mockery;
use Tests\TestCase;

class PiiModulePermissionEligibilityTest extends TestCase
{
    public function test_building_add_or_edit_permission_allows_pii_reveal(): void
    {
        $service = new PiiAccessService();

        $this->assertTrue($service->canUnlock(
            $this->userWithPermissions(['Add Building Structure'])
        ));
        $this->assertTrue($service->canUnlock(
            $this->userWithPermissions(['Edit Building Structure'])
        ));
        $this->assertFalse($service->canUnlock(
            $this->userWithPermissions(['List Building Structures'])
        ));
    }

    public function test_application_add_or_edit_permission_allows_pii_reveal(): void
    {
        $service = new ApplicationPiiAccessService();

        $this->assertTrue($service->canUnlock(
            $this->userWithPermissions(['Add Application'])
        ));
        $this->assertTrue($service->canUnlock(
            $this->userWithPermissions(['Edit Application'])
        ));
        $this->assertFalse($service->canUnlock(
            $this->userWithPermissions(['List Applications'])
        ));
    }

    public function test_property_tax_import_permission_allows_pii_reveal(): void
    {
        $service = new PropertyTaxPiiAccessService();

        $this->assertTrue($service->canUnlock(
            $this->userWithPermissions([
                'Import Property Tax Collection From CSV',
            ])
        ));
        $this->assertFalse($service->canUnlock(
            $this->userWithPermissions(['List Property Tax Collection'])
        ));
    }

    private function userWithPermissions(array $permissions): User
    {
        $user = Mockery::mock(User::class)->makePartial();
        $user->shouldReceive('can')->andReturnUsing(
            static fn (string $permission): bool => in_array(
                $permission,
                $permissions,
                true
            )
        );

        return $user;
    }
}

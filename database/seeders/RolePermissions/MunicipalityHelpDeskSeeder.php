<?php

namespace Database\Seeders\RolePermissions;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class MunicipalityHelpDeskSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
        $roles = [
            [
                'name' => 'Municipality - Help Desk',
            ],
        ];

        foreach ($roles as $role) {
            $createdRole = Role::updateOrCreate($role);
            switch ($createdRole->name) {
                case 'Municipality - Help Desk':
                    $createdRole->givePermissionTo(Permission::all()->whereIn('group', ['Building Structures', 'Help Desks', 'Employee Infos', 'Desludging Vehicles'])
                        ->whereIn('type', ['View', 'List', 'View on map']));

                    $createdRole->givePermissionTo(Permission::all()->whereIn('group', ['Low Income Communities'])->whereIn('type', ['View', 'List', 'Export', 'View on map']));
                    $createdRole->givePermissionTo(Permission::all()->whereIn('group', ['Containments'])->whereIn('type', ['View', 'List', 'Export', 'View on map', 'Service History']));
                    $createdRole->givePermissionTo(Permission::all()->whereIn('group', ['Treatment Plants'])->whereIn('type', ['View', 'List', 'View on map']));
                    $createdRole->givePermissionTo(Permission::all()->whereIn('group', ['Applications', 'Feedbacks'])->whereNotIn('type', ['History']));
                    $createdRole->givePermissionTo(
                        Permission::all()->whereIn('group', ['FSM Dashboard'])
                        ->whereIn('name', [
                            'Sanitation Worker Compliance with PPE Guidelines Chart',
                            'Customer Satisfaction with FSM Service Quality Chart',
                            'Emptying Requests for the Next Four Weeks Chart',
                            'Monthly Emptying Requests Processed by Service Providers Chart',
                            'Summary of Applications, Emptying Services, Sludge Disposal, and Feedback by Ward Chart',
                            'Ward-Wise Distribution of Containment Types Chart','Ward-Wise Distribution of Emptying Requests for the Next Four Weeks Chart'
                        ])
                    );
                    $createdRole->givePermissionTo(Permission::all()->whereIn('group', ['Sludge Collections', 'Emptyings', 'Service Providers'])->whereIn('type', ['View', 'List', 'Export']));
                    $createdRole->givePermissionTo(Permission::all()->whereIn('group', ['Maps'])
                        ->whereIn('name', ['View Building On Map', 'Roads Map Layer', 'Sewers Line Map Layer',
                                           'Places Map Layer', 'Buildings Map Layer', 'Containments Map Layer', 'Sanitation System Map Layer', 'Water Body Map Layer',
                                           'Service Delivery Map Tools', 'Applications Map Tools', 'Emptied Applications Not Reached to TP Map Tools', 
                                           'Containments Proposed To Be Emptied Map Tools', 'Service Feedback Map Tools','Treatment Plants Map Layer',
                        'Municipality Map Layer','Info Map Tools',
                        'Ward Boundary Map Layer',
                        'Low Income Community Map Layer' ]));
                    $createdRole->givePermissionTo(Permission::where('group','Schedule Desludging')->get()
    );
                    $createdRole->givePermissionTo(Permission::where('group','Schedule Reintegration')->get());

                    break;
            }
        }
    }
}

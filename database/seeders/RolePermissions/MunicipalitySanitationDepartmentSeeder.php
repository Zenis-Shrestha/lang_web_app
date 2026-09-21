<?php

namespace Database\Seeders\RolePermissions;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class MunicipalitySanitationDepartmentSeeder extends Seeder
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
                'name' => 'Municipality - Sanitation Department',
            ],
        ];
        foreach ($roles as $role) {
            $createdRole = Role::updateOrCreate($role);
            switch ($createdRole->name) {
                case 'Municipality - Sanitation Department':
                    $createdRole->givePermissionTo(Permission::all()->whereIn('group', [
                        'Building Structures','Low Income Communities',])
                        ->whereIn('type', ['View', 'List', 'Export', 'View on map']));
                    
                    ///FSM////
                    $createdRole->givePermissionTo(Permission::all()->whereIn('group', ['Help Desks', 'Service Providers', 'Treatment Plants',])
                    ->whereNotIn('type', ['History']));
                    //Treatment Plant Efficiency Tests
                    $createdRole->givePermissionTo(Permission::all()->whereIn('group', ['Treatment Plant Efficiency Tests'])
                    ->whereIn('type', ['List','View','Export']));
                    //Treatment Plant Efficiency Standards
                    $createdRole->givePermissionTo(Permission::all()->whereIn('group', ['Treatment Plant Efficiency Standards'])
                        ->whereIn('type', ['View','Edit']));

                    $createdRole->givePermissionTo(Permission::all()->whereIn('group', ['Schedule Reintegration'])
                        ->whereIn('type', ['List']));

                    $createdRole->givePermissionTo(Permission::all()->whereIn('group', ['Containments'])
                        ->whereIn('type', ['View', 'Export', 'List', 'View on map', 'Service History']));
                    
                    //Service Delivery
                    $createdRole->givePermissionTo(Permission::all()->whereIn('group', ['Employee Infos', 'Desludging Vehicles', 'Applications', 'Emptyings', 'Sludge Collections', 'Feedbacks'])
                        ->whereIn('type', ['View', 'Export', 'List', 'Export']));

                    //PT/Ct IMS
                    $createdRole->givePermissionTo(Permission::all()->whereIn('group', ['PT/CT Toilets', 'PT Users Logs'])->whereNotIn('type',['History']));

                    $createdRole->givePermissionTo(Permission::all()->whereIn('group', ['Roads', 'Sewers', 'WaterSupply Network', 'Drain'])
                        ->whereIn('type', ['View', 'List', 'Export', 'View on map']));

                    ///Public Health///
                    $createdRole->givePermissionTo(Permission::all()->whereIn('group', ['Water Samples', 'Hotspots', 'Yearly Waterborne Cases'])
                        ->whereIn('type', ['View', 'List', 'Export', 'View on map']));

                    $createdRole->givePermissionTo(Permission::all()->whereIn('group', ['CWIS', 'KPI Dashboard', 'KPI Target']));

                    $createdRole->givePermissionTo(Permission::all()->whereIn('group', ['NSD Setting'])
                        ->whereIn('type', ['List', 'Save']));

                    $createdRole->givePermissionTo(Permission::all()->whereIn('group', ['NSD'])
                        ->whereIn('type', ['Push', 'Show']));    

                    $createdRole->givePermissionTo(Permission::all()->whereIn('group',['Data Export'])->whereIn('type',['Export']));

                    $createdRole->givePermissionTo(
                        Permission::all()->whereIn('group', ['Maps'])
                            ->whereIn('name', [
                                // Updated list
                                'Containments Map Layer',
                                'Buildings Map Layer',
                                'Water Body Map Layer',
                                'Low Income Status Map Layer',
                                'Water Samples Map Layer',
                                'Info Map Tools',
                                'Service Delivery Map Tools',
                                'Applications Map Tools',
                                'Emptied Applications Map Tools',
                                'Containments Proposed To Be Emptied Map Tools',
                                'Service Feedback Map Tools',
                                'General Map Tools',
                                'Building by Structure Map Tools',
                                'Water Supply Map Tools',
                                'Solid Waste Map Tools',
                                'Data Export Map Tools',
                                'Filter by Wards Map Tools',
                                'Export Data Map Tools',
                                'Owner Information Map Tools',
                                'Decision Map Tools',
                                'Sewer Potential Map Tools',
                                'Buildings to Sewer Map Tools',
                                'Buildings to Road Map Tools',
                                'Hard to Reach Buildings Map Tools',
                                'Building Close to Water Bodies Map Tools',
                                'Community Toilets Map Tools',
                                'Area Population Map Tools',
                                'Summary Information Buffer Map Tools',
                                'Summary Information Water Bodies Map Tools',
                                'Summary Information Wards Map Tools',
                                'Summary Information Road Map Tools',
                                'Summary Information Point Map Tools',
                                'Export in Decision Map Tools',
                                'Export in Summary Information Map Tools',
                                'Public Health Map Layer',
                                'Drains Map Layer',
                                'WaterSupply Network Map Layer',
                                'Treatment Plants Map Layer',
                                'Roads Map Layer',
                                'Places Map Layer',
                                'Land Use Map Layer',
                                'Wards Map Layer',
                                'Summarized Grids Map Layer',
                                'Sewers Line Map Layer',
                                'Sanitation System Map Layer',
                                'Low Income Community Map Layer',
                                'Municipality Map Layer',
                                'PT/CT Toilets Map Layer',
                                'Ward Boundary Map Layer',
                                'Emptied Applications Not Reached to TP Map Tools', 
                                'Containments Emptied Info Map Tools', 
                                'Toilet Isochrone Map Tools',
                                'Export Containment Report'
                            ])
                    );

                    $createdRole->givePermissionTo(Permission::all()->whereIn('group', ['Building Dashboard'])
                        ->whereIn('type', [
                            'CountBox',
                            'Chart',
                        ]));

                    //Dashboard
                    $createdRole->givePermissionTo(Permission::all()->whereIn('group', ['Dashboard'])->whereIn('name', [
                        'Building Connections to Sanitation System Types Chart',
                        'Utility CountBox',
                        'FSM CountBox',
                        'Performance of Municipal Treatment Plants by Last 5 Years Chart',
                        'PTCT CountBox',
                        'Public Health CountBox',
                        'Ward-Wise Revenue Collected from Emptying Services Chart',
                        'Yearly Distribution of Waterborne Disease Chart',
                    ]));


                    $createdRole->givePermissionTo(Permission::all()->whereIn('group', ['FSM Dashboard']));

                    $createdRole->givePermissionTo(Permission::all()->whereIn('group', ['Utility Dashboard'])->whereIn('name', [
                        'Drain Count Box',
                        'Sewer Count Box',
                        'Ward-Wise Drain Length by Size Chart',
                        'Ward-Wise Drain Length by Surface Type Chart',
                        'Ward-Wise Drain Length by Type Chart',
                        'Ward-Wise Drain Length Chart',
                        'Ward-Wise Sewer Length by Diameter Chart',
                        'Ward-Wise Sewer Network Length Chart',
                        'Ward-Wise Water Supply Length by Diameter Chart',
                        'Ward-Wise Water Supply Length Chart',
                        'Water Supply CountBox',
                    ]));

                    ///User Information Managaement///
                    $createdRole->givePermissionTo(Permission::all()->whereIn('group', ['Users'])
                        ->whereIn('type', [
                            'List',
                            'View',
                            'Add',
                            'Edit',
                            'Delete',
                            'Activity'
                        ]));
                    break;
            }
        }
    }
}

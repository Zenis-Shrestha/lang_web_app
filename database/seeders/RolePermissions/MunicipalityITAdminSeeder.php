<?php

namespace Database\Seeders\RolePermissions;

use Illuminate\Database\Seeder;


use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class MunicipalityITAdminSeeder extends Seeder
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
                'name' => 'Municipality - IT Admin',
            ],
        ];
        foreach ($roles as $role) {
            $createdRole = Role::updateOrCreate($role);
            switch ($createdRole->name) {
                case 'Municipality - IT Admin':

                    $createdRole->givePermissionTo(Permission::all()->whereIn('group', [
                        'Building Dashboard',
                        'Dashboard',
                        'FSM Dashboard',
                        'Utility Dashboard',
                        'Language',
                        'Schedule Reintegration',
                    ]));
                    $createdRole->givePermissionTo(Permission::all()->whereIn('group', [
                        'Building Structures',
                        'Low Income Communities',
                    ])
                        ->whereIn('type', ['View', 'List', 'Export', 'History', 'View on map']));
                    $createdRole->givePermissionTo(Permission::all()->whereIn('group', [
                        'Building Surveys',
                    ])->whereIn('type', ['List', 'Download', 'View on map']));
                    //FSM Module
                    $createdRole->givePermissionTo(Permission::all()->whereIn('group', ['Containments'])
                    ->whereIn('type', ['View', 'List', 'Export', 'History', 'View on map', 'Service History'])
                    );
                    $createdRole->givePermissionTo(
                        Permission::all()
                            ->whereIn('group', [
                                'Service Providers',
                                'Treatment Plants',
                                'Treatment Plant Efficiency Tests',
                                'Employee Infos',
                                'Desludging Vehicles',
                                'Applications',
                                'Emptyings',
                                'Feedbacks',
                                'Sludge Collections',
                                'Help Desks'
                            ])
                            ->whereIn('type', ['View', 'List', 'Export', 'History', 'View on map'])
                    );

                    $createdRole->givePermissionTo(Permission::all()->whereIn('group', ['Sewer Connection'])
                            ->whereIn('type', ['List', 'View on map'])
                    );

                    $createdRole->givePermissionTo(Permission::all()->whereIn('group', [
                        'Treatment Plant Efficiency Standards'
                    ])->whereIn('type', ['View']));

                    //CT/PT
                    $createdRole->givePermissionTo(Permission::all()->whereIn('group', ['PT/CT Toilets','PT Users Logs'])
                        ->whereIn('type', ['View', 'List', 'Export', 'History', 'View on map'])
                    );

                    $createdRole->givePermissionTo(Permission::all()->whereIn('group', ['NSD Setting'])
                        ->whereIn('type', ['List', 'Save']));

                    $createdRole->givePermissionTo(Permission::all()->whereIn('group', ['NSD'])
                        ->whereIn('type', ['Show']));

                    //CWIS IMS
                    $createdRole->givePermissionTo(Permission::all()->whereIn('group', ['CWIS'])
                            ->whereIn('type', ['List','View', 'Export'])
                    );

                    //KPI Dashboard
                    $createdRole->givePermissionTo(Permission::all()->whereIn('group', ['KPI Dashboard'])
                    );
                    $createdRole->givePermissionTo(Permission::all()->whereIn('group', ['KPI Target'])
                            ->whereIn('type', ['View', 'List', 'Export'])
                    );

                    //Utility
                    $createdRole->givePermissionTo(Permission::all()->whereIn('group', ['Roads', 'Sewers', 'WaterSupply Network', 'Drain'])
                        ->whereIn('type', ['View', 'List', 'Export', 'View on map', 'History']));

                    //Payment ISS
                    $createdRole->givePermissionTo(Permission::all()->whereIn('group', ['Swm Service Payment', 'Property Tax Collection ISS', 'Water Supply ISS'])
                        ->whereIn('type', ['List', 'Export']));

                    //Public Health
                    $createdRole->givePermissionTo(Permission::all()->whereIn('group', ['Water Samples', 'Hotspots', 'Yearly Waterborne Cases'])
                        ->whereIn('type', ['List', 'View', 'Export', 'View on map', 'History']));

                    //Data Export Urban Management
                    $createdRole->givePermissionTo(Permission::all()->whereIn('group',['Data Export'])->whereIn('type',['Export']));

                    //Maps
                    $createdRole->givePermissionTo(Permission::all()->whereIn('group', ['Maps'])
                        ->whereIn('name', [
                            'Export Data Map Tools',
                            'Containments Map Layer',
                            'Buildings Map Layer',
                            'Tax Payment Status Buildings Map Layer',
                            'Water Payment Status Map Layer',
                            'Treatment Plants Map Layer',
                            'Roads Map Layer',
                            'Places Map Layer',
                            'Land Use Map Layer',
                            'Wards Map Layer',
                            'Summarized Grids Map Layer',
                            'Sewers Line Map Layer',
                            'Sanitation System Map Layer',
                            'Water Samples Map Layer',
                            'Info Map Tools',
                            'Service Delivery Map Tools',
                            'Applications Map Tools',
                            'Emptied Applications Not Reached to TP Map Tools',
                            'Containments Proposed To Be Emptied Map Tools',
                            'Service Feedback Map Tools',
                            'General Map Tools',
                            'Building by Structure Map Tools',
                            'Property Tax Map Tools',
                            'Water Payment Status Map Tool',
                            'Solid Waste Payment Status Map Tool',
                            'Data Export Map Tools',
                            'Filter by Wards Map Tools',
                            'Export Data Map Tools',
                            'Owner Information Map Tools',
                            'Decision Map Tools',
                            'Tax Due Map Tools',
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
                            'Export in General Map Tools',
                            'Export in Decision Map Tools',
                            'Export in Summary Information Map Tools',
                            'Public Health Map Layer',
                            'Drains Map Layer',
                            'View WaterSupply Network On Map',
                            'Water Payment Status Map Tools',
                            'Solid Waste Payment Status Map Tools',
                            'WaterSupply Network Map Layer',
                            'Ward Boundary Map Layer',
                            'Municipality Map Layer',
                            'PT/CT Toilets Map Layer',
                            'Water Body Map Layer',
                            'Solid Waste Status Map Layer',
                            'Low Income Community Map Layer',
                            'Containments Emptied Info Map Tools', 
                            'Toilet Isochrone Map Tools',
                            'Export Containment Report'


                        ]));

                    $createdRole->givePermissionTo(Permission::all()->whereIn('group',['API']));

                    ///User Information Managaement///
                    $createdRole->givePermissionTo(Permission::all()->whereIn('group', ['Roles'])
                        ->whereIn('type', [
                            'List',
                            'View',
                            'Add',
                        ]));
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

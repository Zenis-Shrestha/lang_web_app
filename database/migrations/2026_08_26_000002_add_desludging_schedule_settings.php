<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddDesludgingScheduleSettings extends Migration
{
    private const CATEGORY = 'desludging_schedule';

    /** Add NSD equivalents of the reference project's schedule settings. */
    public function up()
    {
        $now = now();
        $settings = [
            'Schedule Desludging Start Date' => '',
            'Schedule Regeneration Period' => '6',
            'Trip Capacity Per Day' => '20',
            'Weekend' => 'Friday,Saturday',
            'Holiday Dates' => '',
        ];
        $nextId = ((int) DB::table('public.site_settings')->max('id')) + 1;


        foreach ($settings as $name => $value) {
            $exists = DB::table('public.site_settings')
                ->where('category', self::CATEGORY)
                ->where('name', $name)
                ->exists();

            if (!$exists) {
                DB::table('public.site_settings')->insert([
                    'name' => $name,
                    'id' => $nextId++,
                    'value' => $value,
                    'category' => self::CATEGORY,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down()
    {
        DB::table('public.site_settings')
            ->where('category', self::CATEGORY)
            ->whereIn('name', [
                'Schedule Desludging Start Date',
                'Schedule Regeneration Period',
                'Trip Capacity Per Day',
                'Weekend',
                'Holiday Dates',
            ])
            ->delete();
    }
}

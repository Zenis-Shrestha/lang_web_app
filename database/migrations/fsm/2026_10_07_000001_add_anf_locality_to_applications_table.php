<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up()
    {
        DB::statement('ALTER TABLE fsm.applications ADD COLUMN IF NOT EXISTS anf_locality varchar(255)');
    }

    public function down()
    {
        DB::statement('ALTER TABLE fsm.applications DROP COLUMN IF EXISTS anf_locality');
    }
};

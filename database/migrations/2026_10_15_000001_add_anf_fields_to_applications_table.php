<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AddAnfFieldsToApplicationsTable extends Migration
{
    /**
     * Add the fields used by the "House Number Unknown" application flow.
     *
     * Each column is checked separately because some environments may already
     * have received part of the ANF schema through an earlier manual update.
     */
    public function up()
    {
        if (!$this->hasApplicationColumn('is_anf')) {
            Schema::table('fsm.applications', function (Blueprint $table) {
                $table->boolean('is_anf')->default(false);
            });
        }

        if (!$this->hasApplicationColumn('anf_locality')) {
            Schema::table('fsm.applications', function (Blueprint $table) {
                $table->string('anf_locality')->nullable();
            });
        }

        if (!$this->hasApplicationColumn('anf_nearest_locality')) {
            Schema::table('fsm.applications', function (Blueprint $table) {
                $table->string('anf_nearest_locality')->nullable();
            });
        }
    }

    public function down()
    {
        foreach (['anf_nearest_locality', 'anf_locality', 'is_anf'] as $column) {
            if ($this->hasApplicationColumn($column)) {
                Schema::table('fsm.applications', function (Blueprint $table) use ($column) {
                    $table->dropColumn($column);
                });
            }
        }
    }

    /**
     * Laravel's schema inspector does not consistently resolve qualified table
     * names on the PostgreSQL version used by this project, so check the
     * catalog explicitly before applying a column-level migration.
     */
    private function hasApplicationColumn(string $column): bool
    {
        return DB::table('information_schema.columns')
            ->where('table_schema', 'fsm')
            ->where('table_name', 'applications')
            ->where('column_name', $column)
            ->exists();
    }
}

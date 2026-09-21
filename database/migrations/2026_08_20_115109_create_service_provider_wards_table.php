<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateServiceProviderWardsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        DB::statement('CREATE SCHEMA IF NOT EXISTS fsm');

        Schema::create('fsm.service_provider_wards', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('service_provider_id');
            $table->integer('ward');

            $table->timestamps();

            $table->unique(
                ['service_provider_id', 'ward'],
                'service_provider_wards_provider_ward_unique'
            );

            $table->index(
                'service_provider_id',
                'service_provider_wards_provider_idx'
            );

            $table->index(
                'ward',
                'service_provider_wards_ward_idx'
            );
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('fsm.service_provider_wards');
    }
}
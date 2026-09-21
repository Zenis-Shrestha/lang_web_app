<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSchedulingFieldsToContainmentsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('fsm.containments', function (Blueprint $table) {
            $table->smallInteger('status')
                ->nullable()
                ->default(0);

            $table->smallInteger('priority')
                ->nullable();

            $table->decimal('fstp_distance', 12, 2)
                ->nullable();

            $table->bigInteger('closest_fstp_id')
                ->nullable();

            $table->index(
                'status',
                'containments_schedule_status_idx'
            );

            $table->index(
                'priority',
                'containments_schedule_priority_idx'
            );

            $table->index(
                'closest_fstp_id',
                'containments_closest_fstp_idx'
            );

            $table->index(
                ['priority', 'fstp_distance'],
                'containments_priority_distance_idx'
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
        Schema::table('fsm.containments', function (Blueprint $table) {
            $table->dropIndex('containments_schedule_status_idx');
            $table->dropIndex('containments_schedule_priority_idx');
            $table->dropIndex('containments_closest_fstp_idx');
            $table->dropIndex('containments_priority_distance_idx');

            $table->dropColumn([
                'status',
                'priority',
                'fstp_distance',
                'closest_fstp_id',
            ]);
        });
    }
}
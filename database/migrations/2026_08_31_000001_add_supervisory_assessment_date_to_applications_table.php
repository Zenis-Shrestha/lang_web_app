<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddSupervisoryAssessmentDateToApplicationsTable extends Migration
{
    public function up()
    {
        Schema::table('fsm.applications', function (Blueprint $table) {
            $table->date('supervisory_assessment_date')
                ->nullable();
        });
    }

    public function down()
    {
        Schema::table('fsm.applications', function (Blueprint $table) {
            $table->dropColumn('supervisory_assessment_date');
        });
    }
}

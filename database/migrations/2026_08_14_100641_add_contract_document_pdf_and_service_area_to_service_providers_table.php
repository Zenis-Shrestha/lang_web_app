<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddContractDocumentPdfAndServiceAreaToServiceProvidersTable extends Migration
{
    public function up()
    {
        DB::statement("
            ALTER TABLE fsm.service_providers
            ADD COLUMN contract_document_pdf VARCHAR NULL
        ");

        DB::statement("
            ALTER TABLE fsm.service_providers
            ADD COLUMN service_area VARCHAR NULL
        ");
    }

    public function down()
    {
        DB::statement("
            ALTER TABLE fsm.service_providers
            DROP COLUMN IF EXISTS contract_document_pdf
        ");

        DB::statement("
            ALTER TABLE fsm.service_providers
            DROP COLUMN IF EXISTS service_area
        ");
    }
}
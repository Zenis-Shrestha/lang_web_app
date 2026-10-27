<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AddReceiptNumberToSludgeCollectionLogs extends Migration
{
    /**
     * Add the receipt field used by the v2.0.1 sludge-collection workflow.
     * The original log-table migration omitted this field even though both
     * the web and API controllers read, write, filter, and export it.
     */
    public function up()
    {
        DB::statement(
            'ALTER TABLE fsm.sludge_collections_log ' .
            'ADD COLUMN IF NOT EXISTS tipping_fee_receipt_no varchar(255)'
        );
        DB::statement(
            'ALTER TABLE fsm.sludge_collections ' .
            'ADD COLUMN IF NOT EXISTS tipping_fee_receipt_no varchar(255)'
        );
    }

    public function down()
    {
        DB::statement(
            'ALTER TABLE fsm.sludge_collections_log ' .
            'DROP COLUMN IF EXISTS tipping_fee_receipt_no'
        );
        DB::statement(
            'ALTER TABLE fsm.sludge_collections ' .
            'DROP COLUMN IF EXISTS tipping_fee_receipt_no'
        );
    }
}

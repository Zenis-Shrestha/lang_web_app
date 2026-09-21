<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class AllowUnassignedDesludgingScheduleRows extends Migration
{
    /** Allow schedule generation before provider assignment is introduced. */
    public function up()
    {
        DB::statement(<<<'SQL'
ALTER TABLE fsm.desludging_schedule_temp
    ALTER COLUMN service_provider_id DROP NOT NULL
SQL
        );

        DB::statement(<<<'SQL'
ALTER TABLE fsm.desludging_schedule_temp
    ADD COLUMN IF NOT EXISTS next_emptying_date date NULL
SQL
        );

        DB::statement(<<<'SQL'
CREATE INDEX IF NOT EXISTS desludging_schedule_next_emptying_date_idx
    ON fsm.desludging_schedule_temp (next_emptying_date)
SQL
        );
    }

    public function down()
    {
        DB::statement(<<<'SQL'
DROP INDEX IF EXISTS fsm.desludging_schedule_next_emptying_date_idx
SQL
        );

        DB::statement(<<<'SQL'
ALTER TABLE fsm.desludging_schedule_temp
    DROP COLUMN IF EXISTS next_emptying_date
SQL
        );

        // Provider IDs cannot safely be made NOT NULL while unassigned rows exist.
    }
}

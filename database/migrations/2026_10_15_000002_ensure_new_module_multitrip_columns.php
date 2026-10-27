<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class EnsureNewModuleMultitripColumns extends Migration
{
    /**
     * Make the v2.0.1 new-modules schema available to normal deployments.
     *
     * The original migrations live under database/migrations/fsm, which the
     * default Laravel migrate command does not scan recursively. These guarded
     * statements preserve installations that already ran the nested files.
     */
    public function up()
    {
        DB::statement('ALTER TABLE fsm.applications ADD COLUMN IF NOT EXISTS desludging_vehicle_size integer');
        DB::statement('ALTER TABLE fsm.applications ADD COLUMN IF NOT EXISTS trip_count integer');
        DB::statement('ALTER TABLE fsm.applications ADD COLUMN IF NOT EXISTS emptying_status integer');
        DB::statement('ALTER TABLE fsm.applications ADD COLUMN IF NOT EXISTS feedback_status integer');
        DB::statement('ALTER TABLE fsm.applications ADD COLUMN IF NOT EXISTS sludge_collection_status integer');

        DB::statement('ALTER TABLE fsm.emptyings ADD COLUMN IF NOT EXISTS trip_no integer DEFAULT 0');
        DB::statement('ALTER TABLE fsm.emptyings ADD COLUMN IF NOT EXISTS total_time integer');
        DB::statement('UPDATE fsm.emptyings SET trip_no = 0 WHERE trip_no IS NULL');

        DB::statement('ALTER TABLE fsm.sludge_collections ADD COLUMN IF NOT EXISTS trip_no integer');
        DB::statement('ALTER TABLE fsm.sludge_collections ADD COLUMN IF NOT EXISTS tipping_fee_amount integer');
        DB::statement('ALTER TABLE fsm.sludge_collections ADD COLUMN IF NOT EXISTS total_time integer');
    }

    public function down()
    {
        // These columns belong to the pre-existing v2.0.1 new-modules feature.
        // Dropping them here could destroy data on systems that ran its nested
        // migrations first, so rollback requires an explicit reviewed change.
        throw new RuntimeException(
            'New-module multi-trip columns require a reviewed manual rollback.'
        );
    }
}

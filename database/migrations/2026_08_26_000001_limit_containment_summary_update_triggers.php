<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class LimitContainmentSummaryUpdateTriggers extends Migration
{
    /**
     * Spatial summary views depend on geometry, type and soft-deletion state.
     * Priority changes must not rebuild those views once for every row.
     */
    public function up()
    {
        DB::unprepared(<<<'SQL'
DROP TRIGGER IF EXISTS tgr_set_builtupperwardsummary ON fsm.containments;
DROP TRIGGER IF EXISTS tgr_set_landusesummary ON fsm.containments;

DO $$
BEGIN
    IF to_regprocedure('public.fnc_set_builtupperwardsummary()') IS NOT NULL THEN
        CREATE TRIGGER tgr_set_builtupperwardsummary
        AFTER INSERT OR DELETE OR UPDATE OF geom, type_id, deleted_at
        ON fsm.containments
        FOR EACH ROW
        EXECUTE FUNCTION public.fnc_set_builtupperwardsummary();
    END IF;

    IF to_regprocedure('public.fnc_set_landusesummary()') IS NOT NULL THEN
        CREATE TRIGGER tgr_set_landusesummary
        AFTER INSERT OR DELETE OR UPDATE OF geom, type_id, deleted_at
        ON fsm.containments
        FOR EACH ROW
        EXECUTE FUNCTION public.fnc_set_landusesummary();
    END IF;
END
$$;
SQL
        );
    }

    /** Restore the original broad UPDATE behavior. */
    public function down()
    {
        DB::unprepared(<<<'SQL'
DROP TRIGGER IF EXISTS tgr_set_builtupperwardsummary ON fsm.containments;
DROP TRIGGER IF EXISTS tgr_set_landusesummary ON fsm.containments;

DO $$
BEGIN
    IF to_regprocedure('public.fnc_set_builtupperwardsummary()') IS NOT NULL THEN
        CREATE TRIGGER tgr_set_builtupperwardsummary
        AFTER INSERT OR DELETE OR UPDATE
        ON fsm.containments
        FOR EACH ROW
        EXECUTE FUNCTION public.fnc_set_builtupperwardsummary();
    END IF;

    IF to_regprocedure('public.fnc_set_landusesummary()') IS NOT NULL THEN
        CREATE TRIGGER tgr_set_landusesummary
        AFTER INSERT OR DELETE OR UPDATE
        ON fsm.containments
        FOR EACH ROW
        EXECUTE FUNCTION public.fnc_set_landusesummary();
    END IF;
END
$$;
SQL
        );
    }
}

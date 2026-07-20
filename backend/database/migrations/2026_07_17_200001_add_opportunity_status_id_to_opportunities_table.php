<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Backfills `opportunities.opportunity_status_id` as a mandatory FK (spec
 * 0043, D-3/BR-2). The opportunities create-migration is already committed
 * and may already hold rows in production, so the column is added nullable,
 * existing rows are pointed at the system 'new' status via the DB facade
 * (never a Model in a migration), and only then is the column tightened to
 * NOT NULL — all inside one `up()` so the table is never left
 * half-migrated. Mirrors 2026_07_14_160100_add_lead_status_id_to_leads_table
 * verbatim.
 *
 * On an empty table (migrate:fresh, tests) the backfill block is a no-op,
 * keeping the clean seed clean.
 *
 * `->change()` on a FK column is the one risky step: on SQLite, Laravel
 * rebuilds the whole table (no ALTER COLUMN support). That rebuild reads the
 * table's CURRENT foreign keys from the connection's schema state and
 * re-emits them on the rebuilt table, so the `restrictOnDelete` constraint
 * added in the first step survives the rebuild without being redeclared. On
 * MySQL, `MODIFY COLUMN ... NOT NULL` only tightens the null constraint and
 * never touches the separate FK constraint object, so the same is true
 * there. Verified by OpportunityStatusMigrationTest, not assumed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('opportunities', function (Blueprint $table) {
            $table->foreignId('opportunity_status_id')
                ->nullable()
                ->after('lead_id')
                ->constrained('opportunity_statuses')
                ->restrictOnDelete();
        });

        if (DB::table('opportunities')->exists()) {
            $defaultStatusId = DB::table('opportunity_statuses')->where('system_key', 'new')->value('id');

            DB::table('opportunities')->update(['opportunity_status_id' => $defaultStatusId]);
        }

        Schema::table('opportunities', function (Blueprint $table) {
            $table->foreignId('opportunity_status_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('opportunities', function (Blueprint $table) {
            $table->dropForeign(['opportunity_status_id']);
            $table->dropColumn('opportunity_status_id');
        });
    }
};

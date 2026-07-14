<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Backfills `leads.lead_status_id` as a mandatory FK (D-1/BR-1). `leads` may
 * already hold rows in production, so the column is added nullable, existing
 * rows are pointed at a default status created via the DB facade (never a
 * Model in a migration), and only then is the column tightened to NOT NULL —
 * all inside one `up()` so the table is never left half-migrated.
 *
 * On an empty table (migrate:fresh, tests) the backfill block is a no-op: no
 * `lead_statuses` row is created, keeping the clean seed clean.
 *
 * `->change()` on a FK column is the one risky step: on SQLite, Laravel
 * rebuilds the whole table (no ALTER COLUMN support). Doctrine/dbal is NOT
 * a dependency of this app (Laravel 11+ dropped that requirement), so this
 * relies on the framework's own native schema rebuild. That rebuild reads the
 * table's CURRENT foreign keys from the connection's schema state and
 * re-emits them on the rebuilt table (see
 * Illuminate\Database\Schema\BlueprintState), so the `restrictOnDelete`
 * constraint added in step (a) survives the step (c) rebuild without being
 * redeclared. On MySQL, `MODIFY COLUMN ... NOT NULL` only tightens the null
 * constraint and never touches the separate FK constraint object, so the
 * same is true there. Verified by LeadStatusMigrationTest, not assumed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->foreignId('lead_status_id')
                ->nullable()
                ->after('source_id')
                ->constrained('lead_statuses')
                ->restrictOnDelete();
        });

        if (DB::table('leads')->exists()) {
            $defaultStatusId = DB::table('lead_statuses')->insertGetId([
                'name' => 'New',
                'color' => 'slate',
                'sort_order' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('leads')->update(['lead_status_id' => $defaultStatusId]);
        }

        Schema::table('leads', function (Blueprint $table) {
            $table->foreignId('lead_status_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('leads', function (Blueprint $table) {
            $table->dropForeign(['lead_status_id']);
            $table->dropColumn('lead_status_id');
        });
    }
};

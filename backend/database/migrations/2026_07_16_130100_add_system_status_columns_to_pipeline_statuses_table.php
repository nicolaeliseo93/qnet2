<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Introduces the two mandatory system statuses ("Nuovo"/"Chiuso") on
 * `pipeline_statuses` (spec 0039, D-2/D-5) plus the optional classification
 * link to `status_groups` (D-6). Schema and data migration run together in
 * one `up()` so the table is never left half-migrated (precedent:
 * 2026_07_14_160100_add_lead_status_id_to_leads_table.php).
 *
 * Data migration (same logic duplicated in the lead_statuses sibling
 * migration — anonymous migration classes cannot share a trait without a
 * new app/ file, out of this migration's ownership):
 * (1) ensure the "Aperto"/"Chiuso" status groups exist (match by name) —
 *     idempotent across this migration and its lead_statuses sibling, both
 *     of which target the same global `status_groups` table;
 * (2) promote a pre-existing row named exactly "Nuovo"/"Chiuso" (first by
 *     id) to the matching system_key, or create it if absent;
 * (3) resequence: Nuovo=0 (+ group "Aperto"), every other (custom) row
 *     10,20,... preserving its current (sort_order, name, id) order,
 *     Chiuso=max(custom)+10 (+ group "Chiuso").
 *
 * On an empty table this produces exactly the 2 system rows (AC-001). On a
 * populated table it promotes/resequences in place, never duplicating rows
 * (AC-002).
 *
 * down() only drops the FK and the two columns (schema-only reversibility,
 * same precedent as 2026_07_14_160100): the "Nuovo"/"Chiuso" rows and the
 * "Aperto"/"Chiuso" groups created by up() are intentionally left in place,
 * not deleted, so a rollback never destroys data a later migration/seeder
 * may already depend on. The `status_groups` TABLE itself is only dropped
 * by its own create migration's down(), which runs after this one during
 * `migrate:rollback` (reverse chronological order) — by then this
 * migration's status_group_id FK has already been removed, so the drop is
 * safe.
 */
return new class extends Migration
{
    private const string TABLE = 'pipeline_statuses';

    public function up(): void
    {
        Schema::table(self::TABLE, function (Blueprint $table) {
            $table->string('system_key', 16)->nullable()->unique()->after('sort_order');
            $table->foreignId('status_group_id')->nullable()->after('system_key')
                ->constrained('status_groups')->restrictOnDelete();
        });

        $this->migrateSystemStatuses();
    }

    public function down(): void
    {
        Schema::table(self::TABLE, function (Blueprint $table) {
            $table->dropConstrainedForeignId('status_group_id');
            $table->dropUnique(['system_key']);
            $table->dropColumn('system_key');
        });
    }

    private function migrateSystemStatuses(): void
    {
        // Step 1: ensure the two system-status groups exist.
        $openGroupId = $this->ensureGroup('Aperto', 'blue', 0);
        $closedGroupId = $this->ensureGroup('Chiuso', 'green', 10);

        // Step 2: promote the pre-existing row (if any) or create it.
        $newId = $this->promoteOrCreateSystemRow('Nuovo', 'new', 'slate');
        $closedId = $this->promoteOrCreateSystemRow('Chiuso', 'closed', 'green');

        // Step 3: resequence — Nuovo=0, customs 10,20,..., Chiuso=max+10.
        DB::table(self::TABLE)->where('id', $newId)->update([
            'sort_order' => 0,
            'status_group_id' => $openGroupId,
            'updated_at' => now(),
        ]);

        $sortOrder = 10;
        $customRows = DB::table(self::TABLE)
            ->whereNotIn('id', [$newId, $closedId])
            ->orderBy('sort_order')->orderBy('name')->orderBy('id')
            ->get(['id']);

        foreach ($customRows as $customRow) {
            DB::table(self::TABLE)->where('id', $customRow->id)->update([
                'sort_order' => $sortOrder,
                'updated_at' => now(),
            ]);
            $sortOrder += 10;
        }

        DB::table(self::TABLE)->where('id', $closedId)->update([
            'sort_order' => $sortOrder,
            'status_group_id' => $closedGroupId,
            'updated_at' => now(),
        ]);
    }

    private function ensureGroup(string $name, string $color, int $sortOrder): int
    {
        $existing = DB::table('status_groups')->where('name', $name)->first();

        if ($existing !== null) {
            return $existing->id;
        }

        return DB::table('status_groups')->insertGetId([
            'name' => $name,
            'color' => $color,
            'sort_order' => $sortOrder,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function promoteOrCreateSystemRow(string $name, string $systemKey, string $color): int
    {
        $existing = DB::table(self::TABLE)->where('name', $name)->orderBy('id')->first();

        if ($existing !== null) {
            DB::table(self::TABLE)->where('id', $existing->id)->update([
                'system_key' => $systemKey,
                'updated_at' => now(),
            ]);

            return $existing->id;
        }

        return DB::table(self::TABLE)->insertGetId([
            'name' => $name,
            'color' => $color,
            'sort_order' => 0,
            'system_key' => $systemKey,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};

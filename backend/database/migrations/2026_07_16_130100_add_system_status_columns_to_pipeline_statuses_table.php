<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Introduces the two mandatory system statuses ("Nuovo"/"Chiuso") on
 * `pipeline_statuses` (spec 0039, D-2/D-5) plus the fixed 3-value
 * classification `group` (open/pending/closed — App\Enums\StatusGroup;
 * replaces the earlier "status groups" lookup entity). Schema and data
 * migration run together in one `up()` so the table is never left
 * half-migrated (precedent: 2026_07_14_160100_add_lead_status_id_to_leads_table.php).
 *
 * Data migration (same logic duplicated in the lead_statuses sibling
 * migration — anonymous migration classes cannot share a trait without a
 * new app/ file, out of this migration's ownership):
 * (1) promote a pre-existing row named exactly "Nuovo"/"Chiuso" (first by
 *     id) to the matching system_key, or create it if absent;
 * (2) resequence: Nuovo=0 (group open), every other (custom) row
 *     10,20,... preserving its current (sort_order, name, id) order,
 *     Chiuso=max(custom)+10 (group closed).
 *
 * On an empty table this produces exactly the 2 system rows (AC-001). On a
 * populated table it promotes/resequences in place, never duplicating rows
 * (AC-002).
 *
 * down() only drops `group` and the two system-key columns (schema-only
 * reversibility, same precedent as 2026_07_14_160100): the "Nuovo"/"Chiuso"
 * rows created by up() are intentionally left in place, not deleted, so a
 * rollback never destroys data a later migration/seeder may already depend
 * on.
 */
return new class extends Migration
{
    private const string TABLE = 'pipeline_statuses';

    public function up(): void
    {
        Schema::table(self::TABLE, function (Blueprint $table) {
            $table->string('system_key', 16)->nullable()->unique()->after('sort_order');
            $table->string('group', 16)->default('open')->after('system_key');
        });

        $this->migrateSystemStatuses();
    }

    public function down(): void
    {
        Schema::table(self::TABLE, function (Blueprint $table) {
            $table->dropColumn('group');
            $table->dropUnique(['system_key']);
            $table->dropColumn('system_key');
        });
    }

    private function migrateSystemStatuses(): void
    {
        // Step 1: promote the pre-existing row (if any) or create it.
        $newId = $this->promoteOrCreateSystemRow('Nuovo', 'new', 'slate', 'open');
        $closedId = $this->promoteOrCreateSystemRow('Chiuso', 'closed', 'green', 'closed');

        // Step 2: resequence — Nuovo=0, customs 10,20,..., Chiuso=max+10.
        DB::table(self::TABLE)->where('id', $newId)->update([
            'sort_order' => 0,
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
            'updated_at' => now(),
        ]);
    }

    private function promoteOrCreateSystemRow(string $name, string $systemKey, string $color, string $group): int
    {
        $existing = DB::table(self::TABLE)->where('name', $name)->orderBy('id')->first();

        if ($existing !== null) {
            DB::table(self::TABLE)->where('id', $existing->id)->update([
                'system_key' => $systemKey,
                'group' => $group,
                'updated_at' => now(),
            ]);

            return $existing->id;
        }

        return DB::table(self::TABLE)->insertGetId([
            'name' => $name,
            'color' => $color,
            'sort_order' => 0,
            'system_key' => $systemKey,
            'group' => $group,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
};

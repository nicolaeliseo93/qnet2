<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Introduces the two mandatory system statuses ("Nuovo"/"Chiuso") on
 * `lead_statuses` (spec 0039, D-2/D-5) plus the optional classification
 * link to `status_groups` (D-6). Sibling of
 * 2026_07_16_130100_add_system_status_columns_to_pipeline_statuses_table.php
 * — same data-migration logic (duplicated: anonymous migration classes
 * cannot share a trait without a new app/ file, out of this migration's
 * ownership), applied to `lead_statuses` instead. `ensureGroup()` still
 * matches "Aperto"/"Chiuso" by name against the global `status_groups`
 * table, so running after the pipeline_statuses sibling reuses the same two
 * group rows rather than duplicating them.
 *
 * See the pipeline_statuses sibling for the full (1)/(2)/(3) data-migration
 * rationale and the down()-reversibility note (schema-only: rows/groups
 * created by up() are intentionally left in place).
 */
return new class extends Migration
{
    private const string TABLE = 'lead_statuses';

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

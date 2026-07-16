<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Introduces the THREE mandatory system statuses on `lead_statuses` (spec
 * 0039 pivot, D-2): "Nuovo" starts the workflow, "Chiuso con successo"
 * (`won`) and "Scartato" (`discarded`) both end it — unlike the
 * pipeline_statuses sibling, which keeps a single "Chiuso" terminal row.
 * Also adds the fixed 3-value classification `group` (open/pending/closed —
 * App\Enums\StatusGroup; replaces the earlier "status groups" lookup
 * entity).
 *
 * Data migration:
 * (1) promote/create "Nuovo" -> `new`, group open, sort 0;
 * (2) promote/rename "Scartato" (`discarded`, group closed): a pre-existing
 *     row already named "Scartato" is preferred (collision guard — its name
 *     is already unique, renaming a different row into it would violate
 *     the constraint); otherwise the pre-existing "Chiuso" row is renamed
 *     "Scartato" and promoted (D-2: this IS the old terminal row, carried
 *     forward under its new name/key); otherwise create it (red);
 * (3) promote/create "Chiuso con successo" (`won`, group closed, green) —
 *     always a fresh/matched-by-name row, never the renamed one;
 * (4) resequence: Nuovo=0, every other (custom) row 10,20,... preserving
 *     its current (sort_order, name, id) order, won=max(custom)+10,
 *     discarded=max(custom)+20 — Scartato is ALWAYS last.
 *
 * On an empty table this produces exactly the 3 system rows (AC-001). On a
 * populated table it promotes/renames/resequences in place, never
 * duplicating rows (AC-002).
 *
 * down() only drops `group` and the two system-key columns (schema-only
 * reversibility, same precedent as 2026_07_14_160100): the system rows
 * created by up() are intentionally left in place, not deleted, so a
 * rollback never destroys data a later migration/seeder may already depend
 * on.
 */
return new class extends Migration
{
    private const string TABLE = 'lead_statuses';

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
        // Step 1: promote/create "Nuovo".
        $newId = $this->promoteOrCreateSystemRow('Nuovo', 'new', 'slate', 'open');

        // Step 2: promote/rename "Scartato" (the old "Chiuso" terminal row).
        $discardedId = $this->promoteOrRenameDiscardedRow();

        // Step 3: promote/create "Chiuso con successo".
        $wonId = $this->promoteOrCreateSystemRow('Chiuso con successo', 'won', 'green', 'closed');

        // Step 4: resequence — Nuovo=0, customs 10,20,..., won=max+10,
        // discarded=max+20 (Scartato ALWAYS last).
        DB::table(self::TABLE)->where('id', $newId)->update([
            'sort_order' => 0,
            'updated_at' => now(),
        ]);

        $sortOrder = 10;
        $customRows = DB::table(self::TABLE)
            ->whereNotIn('id', [$newId, $wonId, $discardedId])
            ->orderBy('sort_order')->orderBy('name')->orderBy('id')
            ->get(['id']);

        foreach ($customRows as $customRow) {
            DB::table(self::TABLE)->where('id', $customRow->id)->update([
                'sort_order' => $sortOrder,
                'updated_at' => now(),
            ]);
            $sortOrder += 10;
        }

        DB::table(self::TABLE)->where('id', $wonId)->update([
            'sort_order' => $sortOrder,
            'updated_at' => now(),
        ]);

        DB::table(self::TABLE)->where('id', $discardedId)->update([
            'sort_order' => $sortOrder + 10,
            'updated_at' => now(),
        ]);
    }

    /**
     * A pre-existing row already named "Scartato" is preferred over renaming
     * "Chiuso" into it (collision guard: `name` is UNIQUE, so renaming a
     * DIFFERENT row into an already-taken name would fail). Otherwise the
     * pre-existing "Chiuso" row (if any) is renamed/promoted; otherwise the
     * row is created fresh.
     */
    private function promoteOrRenameDiscardedRow(): int
    {
        $existingScartato = DB::table(self::TABLE)->where('name', 'Scartato')->orderBy('id')->first();

        if ($existingScartato !== null) {
            DB::table(self::TABLE)->where('id', $existingScartato->id)->update([
                'system_key' => 'discarded',
                'group' => 'closed',
                'updated_at' => now(),
            ]);

            return $existingScartato->id;
        }

        $existingChiuso = DB::table(self::TABLE)->where('name', 'Chiuso')->orderBy('id')->first();

        if ($existingChiuso !== null) {
            DB::table(self::TABLE)->where('id', $existingChiuso->id)->update([
                'name' => 'Scartato',
                'color' => $existingChiuso->color ?? 'red',
                'system_key' => 'discarded',
                'group' => 'closed',
                'updated_at' => now(),
            ]);

            return $existingChiuso->id;
        }

        return DB::table(self::TABLE)->insertGetId([
            'name' => 'Scartato',
            'color' => 'red',
            'sort_order' => 0,
            'system_key' => 'discarded',
            'group' => 'closed',
            'created_at' => now(),
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

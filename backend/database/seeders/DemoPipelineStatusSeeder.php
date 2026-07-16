<?php

namespace Database\Seeders;

use App\Models\PipelineStatus;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seed the project status lookup (spec 0023) with a realistic Italian
 * lifecycle, shared by Projects and Campaigns. `color` uses one of the enum
 * badge tokens from `BADGE_COLOR_TOKENS`
 * (frontend/src/features/custom-fields/badge-color-tokens.ts) — the grid
 * badge/ColorTokenPicker look the value up by TOKEN NAME, never an arbitrary
 * hex. Idempotent: `updateOrCreate` keyed by `name`, so re-running never
 * duplicates rows and refreshes color/sort_order in place.
 *
 * Spec 0039: the two system statuses ("Nuovo"/"Chiuso") are created by the
 * migration, not here — none of these custom rows reuse those names.
 * `sort_order` starts at 10 (0 is reserved for "Nuovo") and `status_group_id`
 * (not Eloquent-fillable yet — pending backend teammate) is assigned via the
 * DB facade after the upsert. The "Chiuso" system row is pushed to
 * max(custom)+10 at the end so it stays last, matching D-5's placement rule.
 */
class DemoPipelineStatusSeeder extends Seeder
{
    /**
     * @var array<int, array{name: string, color: string, sort_order: int, group: string}>
     */
    private const array STATUSES = [
        ['name' => 'Bozza', 'color' => 'slate', 'sort_order' => 10, 'group' => 'Aperto'],
        ['name' => 'In valutazione', 'color' => 'amber', 'sort_order' => 20, 'group' => 'Pending'],
        ['name' => 'Approvato', 'color' => 'blue', 'sort_order' => 30, 'group' => 'Aperto'],
        ['name' => 'In corso', 'color' => 'green', 'sort_order' => 40, 'group' => 'In lavorazione'],
        ['name' => 'Sospeso', 'color' => 'orange', 'sort_order' => 50, 'group' => 'Sospeso'],
        ['name' => 'Concluso', 'color' => 'teal', 'sort_order' => 60, 'group' => 'Chiuso'],
        ['name' => 'Annullato', 'color' => 'red', 'sort_order' => 70, 'group' => 'Chiuso'],
    ];

    public function run(): void
    {
        // Step 1: upsert the custom rows (name/color/sort_order only — the
        // Eloquent-fillable fields).
        foreach (self::STATUSES as $status) {
            PipelineStatus::query()->updateOrCreate(
                ['name' => $status['name']],
                ['color' => $status['color'], 'sort_order' => $status['sort_order']],
            );
        }

        // Step 2: assign each row's status group by name.
        foreach (self::STATUSES as $status) {
            $groupId = DB::table('status_groups')->where('name', $status['group'])->value('id');

            DB::table('pipeline_statuses')->where('name', $status['name'])->update([
                'status_group_id' => $groupId,
                'updated_at' => now(),
            ]);
        }

        // Step 3: keep the system "Chiuso" row last (D-5: Chiuso=max(custom)+10).
        $maxCustomSortOrder = collect(self::STATUSES)->max('sort_order');

        DB::table('pipeline_statuses')->where('system_key', 'closed')->update([
            'sort_order' => $maxCustomSortOrder + 10,
            'updated_at' => now(),
        ]);
    }
}

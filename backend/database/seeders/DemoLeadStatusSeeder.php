<?php

namespace Database\Seeders;

use App\Models\LeadStatus;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seed the lead status lookup (spec 0029) with a realistic working-state
 * progression. `color` uses one of the badge tokens from
 * `BADGE_COLOR_TOKENS` (frontend/src/features/custom-fields/badge-color-tokens.ts)
 * — the grid badge/ColorTokenPicker look the value up by TOKEN NAME, never
 * an arbitrary hex. Idempotent: `updateOrCreate` keyed by `name`, so
 * re-running never duplicates rows and refreshes color/sort_order/group in
 * place.
 *
 * Spec 0039 pivot: the three system statuses ("Nuovo"/"Chiuso con successo"/
 * "Scartato") are created by the migration, not here. The former "Won"/
 * "Lost" custom demo rows are GONE — they duplicated the two system closed
 * rows once "Chiuso con successo"/"Scartato" existed. `sort_order` starts at
 * 10 (0 is reserved for "Nuovo"); the "Chiuso con successo"/"Scartato"
 * system rows are pushed to max(custom)+10/+20 at the end so "Scartato"
 * always stays last, matching D-5's placement rule.
 */
class DemoLeadStatusSeeder extends Seeder
{
    /**
     * @var array<int, array{name: string, color: string, sort_order: int, group: string}>
     */
    private const array STATUSES = [
        ['name' => 'New', 'color' => 'slate', 'sort_order' => 10, 'group' => 'open'],
        ['name' => 'Contacted', 'color' => 'blue', 'sort_order' => 20, 'group' => 'open'],
        ['name' => 'Qualified', 'color' => 'teal', 'sort_order' => 30, 'group' => 'open'],
        ['name' => 'Proposal sent', 'color' => 'amber', 'sort_order' => 40, 'group' => 'pending'],
    ];

    public function run(): void
    {
        // Step 1: upsert the custom rows.
        foreach (self::STATUSES as $status) {
            LeadStatus::query()->updateOrCreate(
                ['name' => $status['name']],
                ['color' => $status['color'], 'sort_order' => $status['sort_order'], 'group' => $status['group']],
            );
        }

        // Step 2: keep the system rows last, "Scartato" always after "Chiuso
        // con successo" (D-5: won=max(custom)+10, discarded=max(custom)+20).
        $maxCustomSortOrder = collect(self::STATUSES)->max('sort_order');

        DB::table('lead_statuses')->where('system_key', 'won')->update([
            'sort_order' => $maxCustomSortOrder + 10,
            'updated_at' => now(),
        ]);

        DB::table('lead_statuses')->where('system_key', 'discarded')->update([
            'sort_order' => $maxCustomSortOrder + 20,
            'updated_at' => now(),
        ]);
    }
}

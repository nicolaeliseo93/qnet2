<?php

namespace Database\Seeders;

use App\Models\OpportunityStatus;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seed the opportunity status lookup (spec 0043) with a realistic
 * working-state progression, mirroring DemoLeadStatusSeeder. `color` uses
 * one of the badge tokens from `BADGE_COLOR_TOKENS`
 * (frontend/src/features/custom-fields/badge-color-tokens.ts) — the grid
 * badge/ColorTokenPicker look the value up by TOKEN NAME, never an arbitrary
 * hex. Idempotent: `updateOrCreate` keyed by `name`, so re-running never
 * duplicates rows and refreshes color/sort_order/group in place.
 *
 * The three system statuses ("Nuova"/"Chiusa con successo"/"Persa") are
 * created by the create-table migration, not here (BR-1). `sort_order`
 * starts at 10 (0 is reserved for "Nuova"); the "Chiusa con successo"/"Persa"
 * system rows are pushed to max(custom)+10/+20 at the end so "Persa" always
 * stays last, matching D-2's placement rule.
 */
class DemoOpportunityStatusSeeder extends Seeder
{
    /**
     * @var array<int, array{name: string, color: string, sort_order: int, group: string}>
     */
    private const array STATUSES = [
        ['name' => 'In trattativa', 'color' => 'blue', 'sort_order' => 10, 'group' => 'open'],
        ['name' => 'Proposta inviata', 'color' => 'amber', 'sort_order' => 20, 'group' => 'pending'],
        ['name' => 'In negoziazione', 'color' => 'teal', 'sort_order' => 30, 'group' => 'pending'],
    ];

    public function run(): void
    {
        // Step 1: upsert the custom rows.
        foreach (self::STATUSES as $status) {
            OpportunityStatus::query()->updateOrCreate(
                ['name' => $status['name']],
                ['color' => $status['color'], 'sort_order' => $status['sort_order'], 'group' => $status['group']],
            );
        }

        // Step 2: keep the system rows last, "Persa" always after "Chiusa
        // con successo" (D-2: won=max(custom)+10, lost=max(custom)+20).
        $maxCustomSortOrder = collect(self::STATUSES)->max('sort_order');

        DB::table('opportunity_statuses')->where('system_key', 'won')->update([
            'sort_order' => $maxCustomSortOrder + 10,
            'updated_at' => now(),
        ]);

        DB::table('opportunity_statuses')->where('system_key', 'lost')->update([
            'sort_order' => $maxCustomSortOrder + 20,
            'updated_at' => now(),
        ]);
    }
}

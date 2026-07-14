<?php

namespace Database\Seeders;

use App\Models\LeadStatus;
use Illuminate\Database\Seeder;

/**
 * Seed the lead status lookup (spec 0029) with a realistic working-state
 * progression. `color` uses one of the badge tokens from
 * `BADGE_COLOR_TOKENS` (frontend/src/features/custom-fields/badge-color-tokens.ts)
 * — the grid badge/ColorTokenPicker look the value up by TOKEN NAME, never
 * an arbitrary hex. Idempotent: `updateOrCreate` keyed by `name`, so
 * re-running never duplicates rows and refreshes color/sort_order in place.
 */
class DemoLeadStatusSeeder extends Seeder
{
    /**
     * @var array<int, array{name: string, color: string, sort_order: int}>
     */
    private const array STATUSES = [
        ['name' => 'New', 'color' => 'slate', 'sort_order' => 0],
        ['name' => 'Contacted', 'color' => 'blue', 'sort_order' => 10],
        ['name' => 'Qualified', 'color' => 'teal', 'sort_order' => 20],
        ['name' => 'Proposal sent', 'color' => 'amber', 'sort_order' => 30],
        ['name' => 'Won', 'color' => 'green', 'sort_order' => 40],
        ['name' => 'Lost', 'color' => 'red', 'sort_order' => 50],
    ];

    public function run(): void
    {
        foreach (self::STATUSES as $status) {
            LeadStatus::query()->updateOrCreate(
                ['name' => $status['name']],
                ['color' => $status['color'], 'sort_order' => $status['sort_order']],
            );
        }
    }
}

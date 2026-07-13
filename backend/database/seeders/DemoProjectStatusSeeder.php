<?php

namespace Database\Seeders;

use App\Models\ProjectStatus;
use Illuminate\Database\Seeder;

/**
 * Seed the project status lookup (spec 0023) with a realistic Italian
 * lifecycle, shared by Projects and Campaigns. `color` uses one of the enum
 * badge tokens from `BADGE_COLOR_TOKENS`
 * (frontend/src/features/custom-fields/badge-color-tokens.ts) — the grid
 * badge/ColorTokenPicker look the value up by TOKEN NAME, never an arbitrary
 * hex. Idempotent: `updateOrCreate` keyed by `name`, so re-running never
 * duplicates rows and refreshes color/sort_order in place.
 */
class DemoProjectStatusSeeder extends Seeder
{
    /**
     * @var array<int, array{name: string, color: string, sort_order: int}>
     */
    private const array STATUSES = [
        ['name' => 'Bozza', 'color' => 'slate', 'sort_order' => 0],
        ['name' => 'In valutazione', 'color' => 'amber', 'sort_order' => 10],
        ['name' => 'Approvato', 'color' => 'blue', 'sort_order' => 20],
        ['name' => 'In corso', 'color' => 'green', 'sort_order' => 30],
        ['name' => 'Sospeso', 'color' => 'orange', 'sort_order' => 40],
        ['name' => 'Concluso', 'color' => 'teal', 'sort_order' => 50],
        ['name' => 'Annullato', 'color' => 'red', 'sort_order' => 60],
    ];

    public function run(): void
    {
        foreach (self::STATUSES as $status) {
            ProjectStatus::query()->updateOrCreate(
                ['name' => $status['name']],
                ['color' => $status['color'], 'sort_order' => $status['sort_order']],
            );
        }
    }
}

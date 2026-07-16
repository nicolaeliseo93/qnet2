<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seed a few custom status groups (spec 0039, D-6) beyond the two mandatory
 * ones ("Aperto"/"Chiuso") already created by the
 * 2026_07_16_130100/130200_add_system_status_columns_to_*_statuses_table
 * migrations. Uses the DB facade rather than an Eloquent model:
 * `App\Models\StatusGroup` is backend-teammate ownership and has not landed
 * yet — mirrors the same technique those migrations already use.
 * Idempotent: matched/updated by `name`, so re-running never duplicates rows.
 */
class DemoStatusGroupSeeder extends Seeder
{
    /**
     * @var array<int, array{name: string, color: string, sort_order: int}>
     */
    private const array GROUPS = [
        ['name' => 'In lavorazione', 'color' => 'amber', 'sort_order' => 20],
        ['name' => 'Pending', 'color' => 'orange', 'sort_order' => 30],
        ['name' => 'Sospeso', 'color' => 'red', 'sort_order' => 40],
    ];

    public function run(): void
    {
        foreach (self::GROUPS as $group) {
            $existing = DB::table('status_groups')->where('name', $group['name'])->first();

            if ($existing !== null) {
                DB::table('status_groups')->where('id', $existing->id)->update([
                    'color' => $group['color'],
                    'sort_order' => $group['sort_order'],
                    'updated_at' => now(),
                ]);

                continue;
            }

            DB::table('status_groups')->insert([
                'name' => $group['name'],
                'color' => $group['color'],
                'sort_order' => $group['sort_order'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}

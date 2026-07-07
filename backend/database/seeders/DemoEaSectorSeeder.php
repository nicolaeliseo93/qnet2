<?php

namespace Database\Seeders;

use App\Models\EaSector;
use Illuminate\Database\Seeder;

/**
 * Seed the initial EA sector tree (spec 0018). Two-level hierarchy: each key of
 * the map is a top-level sector, its values are the children nested under it.
 * Idempotent: every node is created only if a row with that name does not
 * already exist, so re-running never duplicates rows nor overwrites manual edits
 * made through the `ea-sectors` CRUD module.
 */
class DemoEaSectorSeeder extends Seeder
{
    /**
     * @var array<string, array<int, string>>
     */
    private const array SECTORS = [
        'Manufacturing' => [
            'Food & Beverage',
            'Machinery',
            'Textile & Apparel',
            'Electronics',
        ],
        'Construction' => [
            'Residential',
            'Civil Engineering',
            'Installations',
        ],
        'Wholesale & Retail' => [
            'Retail Trade',
            'Wholesale Trade',
            'E-commerce',
        ],
        'Services' => [
            'Consulting',
            'IT & Software',
            'Logistics',
            'Tourism & Hospitality',
        ],
        'Agriculture' => [
            'Crops',
            'Livestock',
            'Forestry',
        ],
    ];

    public function run(): void
    {
        foreach (self::SECTORS as $parentName => $children) {
            $parent = EaSector::firstOrCreate(['name' => $parentName], ['parent_id' => null]);

            foreach ($children as $childName) {
                EaSector::firstOrCreate(['name' => $childName], ['parent_id' => $parent->id]);
            }
        }
    }
}

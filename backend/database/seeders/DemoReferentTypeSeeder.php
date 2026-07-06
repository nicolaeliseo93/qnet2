<?php

namespace Database\Seeders;

use App\Models\ReferentType;
use Illuminate\Database\Seeder;

/**
 * Seed the initial referent type catalogue (spec 0016). Idempotent: each
 * type is created only if a row with that name does not already exist, so
 * re-running never duplicates rows nor overwrites manual edits made through
 * the `referent-types` CRUD module.
 */
class DemoReferentTypeSeeder extends Seeder
{
    /**
     * @var array<int, string>
     */
    private const array TYPES = [
        'Commercial',
        'Technical',
        'Administrative',
        'Legal',
        'Other',
    ];

    public function run(): void
    {
        foreach (self::TYPES as $name) {
            ReferentType::firstOrCreate(['name' => $name]);
        }
    }
}

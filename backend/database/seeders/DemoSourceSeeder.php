<?php

namespace Database\Seeders;

use App\Models\Source;
use Illuminate\Database\Seeder;

/**
 * Seed the initial source catalogue (spec 0018). Idempotent: each source is
 * created only if a row with that name does not already exist, so
 * re-running never duplicates rows nor overwrites manual edits made through
 * the `sources` CRUD module.
 */
class DemoSourceSeeder extends Seeder
{
    /**
     * @var array<int, string>
     */
    private const array SOURCES = [
        'Website',
        'Referral',
        'Trade Show',
        'Cold Call',
        'Social',
        'Partner',
    ];

    public function run(): void
    {
        foreach (self::SOURCES as $name) {
            Source::firstOrCreate(['name' => $name]);
        }
    }
}

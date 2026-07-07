<?php

namespace Database\Seeders;

use App\Models\Tag;
use Illuminate\Database\Seeder;

/**
 * Seed the initial tag catalogue (spec 0019). Idempotent: each tag is created
 * only if a row with that name does not already exist, so re-running never
 * duplicates rows nor overwrites manual edits made through the `tags` CRUD
 * module.
 */
class DemoTagSeeder extends Seeder
{
    /**
     * @var array<int, string>
     */
    private const array TAGS = [
        'Prospect',
        'Customer',
        'Supplier',
        'Partner',
        'VIP',
        'Inactive',
        'Follow Up',
        'Newsletter',
    ];

    public function run(): void
    {
        foreach (self::TAGS as $name) {
            Tag::firstOrCreate(['name' => $name]);
        }
    }
}

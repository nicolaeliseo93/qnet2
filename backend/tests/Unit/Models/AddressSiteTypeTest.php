<?php

use App\Enums\SiteTypeEnum;
use App\Models\Address;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

// Touches the database (migrations, factories), so bind the full TestCase +
// RefreshDatabase explicitly (the default Pest binding only applies to the
// Feature suite — see tests/Pest.php).
uses(TestCase::class, RefreshDatabase::class);

// ---------------------------------------------------------------------------
// AC-002 (spec 0020) — schema
// ---------------------------------------------------------------------------

it('adds addresses.site_type as a NOT NULL column defaulting to billing', function () {
    expect(Schema::hasColumn('addresses', 'site_type'))->toBeTrue();

    $id = DB::table('addresses')->insertGetId([
        'line1' => 'Via Roma 1',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(DB::table('addresses')->find($id)->site_type)->toBe('billing');
});

it('rolls back cleanly: down() drops site_type', function () {
    // Load the migration FILE directly and invoke down()/up() on it, rather
    // than `migrate:rollback --step=1` (which targets the LAST migration in
    // the batch — a moving target as later migrations are added, e.g. the
    // registries ones dated after this one). Deterministic regardless of
    // what else has migrated since.
    $migration = require database_path('migrations/2026_07_08_090000_add_site_type_to_addresses_table.php');

    $migration->down();
    expect(Schema::hasColumn('addresses', 'site_type'))->toBeFalse();

    // Restore the schema for any test running after this one in the same process.
    $migration->up();
    expect(Schema::hasColumn('addresses', 'site_type'))->toBeTrue();
});

// ---------------------------------------------------------------------------
// AC-002/AC-007-style — model cast
// ---------------------------------------------------------------------------

it('casts site_type to SiteTypeEnum on the model', function () {
    $address = Address::factory()->create(['site_type' => 'legal_seat']);

    expect($address->fresh()->site_type)->toBe(SiteTypeEnum::LegalSeat);
});

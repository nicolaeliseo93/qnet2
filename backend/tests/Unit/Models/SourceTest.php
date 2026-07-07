<?php

use App\Models\Concerns\LogsModelActivity;
use App\Models\Source;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

// Touches the database (migrations, factories), so bind the full TestCase +
// RefreshDatabase explicitly (the default Pest binding only applies to the
// Feature suite — see tests/Pest.php).
uses(TestCase::class, RefreshDatabase::class);

// ---------------------------------------------------------------------------
// AC-001 — schema
// ---------------------------------------------------------------------------

it('creates the sources table with the expected columns', function () {
    expect(Schema::hasTable('sources'))->toBeTrue();
    expect(Schema::hasColumns('sources', ['id', 'name', 'created_at', 'updated_at']))->toBeTrue();
});

it('name is required at the database level', function () {
    expect(fn () => DB::table('sources')->insert(['created_at' => now(), 'updated_at' => now()]))
        ->toThrow(QueryException::class);
});

it('down() reverses the migration, up() recreates it', function () {
    $migration = require database_path('migrations/2026_07_07_110800_create_sources_table.php');

    $migration->down();

    expect(Schema::hasTable('sources'))->toBeFalse();

    $migration->up();

    expect(Schema::hasTable('sources'))->toBeTrue();
});

// ---------------------------------------------------------------------------
// AC-002 — model activity log
// ---------------------------------------------------------------------------

it('logs model activity on the sources log channel', function () {
    expect(class_uses(Source::class))->toHaveKey(LogsModelActivity::class);
});

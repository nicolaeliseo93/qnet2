<?php

use App\Models\Concerns\LogsModelActivity;
use App\Models\Referent;
use App\Models\ReferentType;
use Illuminate\Database\Eloquent\Relations\HasMany;
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

it('creates the referent_types table with the expected columns', function () {
    expect(Schema::hasTable('referent_types'))->toBeTrue();
    expect(Schema::hasColumns('referent_types', ['id', 'name', 'created_at', 'updated_at']))->toBeTrue();
});

it('name is required at the database level', function () {
    expect(fn () => DB::table('referent_types')->insert(['created_at' => now(), 'updated_at' => now()]))
        ->toThrow(QueryException::class);
});

it('down() reverses the migration, up() recreates it', function () {
    $migration = require database_path('migrations/2026_07_07_100000_create_referent_types_table.php');

    // The referents table FKs referent_types, so it must be dropped first.
    Schema::dropIfExists('referents');
    $migration->down();

    expect(Schema::hasTable('referent_types'))->toBeFalse();

    $migration->up();

    expect(Schema::hasTable('referent_types'))->toBeTrue();
});

// ---------------------------------------------------------------------------
// AC-002 — model relations and activity log
// ---------------------------------------------------------------------------

it('referents() is a HasMany relation to Referent', function () {
    $relation = (new ReferentType)->referents();

    expect($relation)->toBeInstanceOf(HasMany::class);
    expect($relation->getRelated())->toBeInstanceOf(Referent::class);
});

it('deleting a type nulls out referent_type_id on its referents (nullOnDelete)', function () {
    $type = ReferentType::factory()->create();
    $referent = Referent::factory()->create(['referent_type_id' => $type->id]);

    $type->delete();

    expect($referent->fresh()->referent_type_id)->toBeNull();
});

it('logs model activity on the referent_types log channel', function () {
    expect(class_uses(ReferentType::class))->toHaveKey(LogsModelActivity::class);
});

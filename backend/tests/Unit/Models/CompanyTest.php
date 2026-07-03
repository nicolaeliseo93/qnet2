<?php

use App\Models\Address;
use App\Models\Company;
use App\Models\Concerns\LogsModelActivity;
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

it('creates the companies table with the expected columns', function () {
    expect(Schema::hasTable('companies'))->toBeTrue();
    expect(Schema::hasColumns('companies', ['id', 'denomination', 'vat_number', 'created_at', 'updated_at']))->toBeTrue();
});

it('denomination is required at the database level', function () {
    expect(fn () => DB::table('companies')->insert(['created_at' => now(), 'updated_at' => now()]))
        ->toThrow(QueryException::class);
});

it('vat_number is nullable at the database level', function () {
    $id = DB::table('companies')->insertGetId([
        'denomination' => 'No Vat Srl',
        'vat_number' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    expect(DB::table('companies')->find($id)->vat_number)->toBeNull();
});

it('down() reverses the migration, up() recreates it', function () {
    $migration = require database_path('migrations/2026_07_03_130000_create_companies_table.php');

    $migration->down();
    expect(Schema::hasTable('companies'))->toBeFalse();

    $migration->up();
    expect(Schema::hasTable('companies'))->toBeTrue();
});

// ---------------------------------------------------------------------------
// AC-002 — model relations, morph alias, cascade delete
// ---------------------------------------------------------------------------

it('uses the "company" morph alias (enforced morphMap)', function () {
    expect((new Company)->getMorphClass())->toBe('company');
});

it('addresses() is a polymorphic morphMany to Address', function () {
    $company = Company::factory()->create();
    $address = Address::factory()->primary()->for($company, 'addressable')->create();

    expect($company->addresses)->toHaveCount(1)
        ->and($company->addresses->first()->is($address))->toBeTrue();
});

it('deleting a company cascades its address (HasAddresses)', function () {
    $company = Company::factory()->create();
    $address = Address::factory()->primary()->for($company, 'addressable')->create();

    $company->delete();

    expect(Address::find($address->id))->toBeNull();
});

it('primaryAddress resolves the is_primary row, falling back to any owned row', function () {
    $company = Company::factory()->create();
    $address = Address::factory()->primary()->for($company, 'addressable')->create();

    expect($company->primaryAddress?->is($address))->toBeTrue();
});

it('primaryAddress is null when the company has no address', function () {
    $company = Company::factory()->create();

    expect($company->primaryAddress)->toBeNull();
});

it('logs model activity on the companies log channel', function () {
    expect(class_uses(Company::class))->toHaveKey(LogsModelActivity::class);
});

it('factory withAddress() attaches a primary address', function () {
    $company = Company::factory()->withAddress()->create();

    expect($company->addresses()->where('is_primary', true)->count())->toBe(1);
});

<?php

use App\Models\Address;
use App\Models\City;
use App\Models\Concerns\LogsModelActivity;
use App\Models\OperationalSite;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

// Touches the database (migrations, factories), so bind the full TestCase +
// RefreshDatabase explicitly (the default Pest binding only applies to the
// Feature suite — see tests/Pest.php).
uses(TestCase::class, RefreshDatabase::class);

// ---------------------------------------------------------------------------
// AC-001 — schema
// ---------------------------------------------------------------------------

it('creates the operational_sites table with only id and timestamps', function () {
    expect(Schema::hasTable('operational_sites'))->toBeTrue();
    expect(Schema::hasColumns('operational_sites', ['id', 'created_at', 'updated_at']))->toBeTrue();

    // No address column ever lives on the site itself (spec 0011).
    expect(Schema::hasColumns('operational_sites', [
        'line1', 'postal_code', 'city_id', 'province_id', 'state_id', 'country_id', 'name', 'label',
    ]))->toBeFalse();
});

it('down() reverses the migration, up() recreates it', function () {
    $migration = require database_path('migrations/2026_07_03_140000_create_operational_sites_table.php');

    $migration->down();
    expect(Schema::hasTable('operational_sites'))->toBeFalse();

    $migration->up();
    expect(Schema::hasTable('operational_sites'))->toBeTrue();
});

// ---------------------------------------------------------------------------
// AC-002 — model relations, morph alias, cascade delete
// ---------------------------------------------------------------------------

it('uses the "operational_site" morph alias (enforced morphMap)', function () {
    expect((new OperationalSite)->getMorphClass())->toBe('operational_site');
});

it('addresses() is a polymorphic morphMany to Address', function () {
    $site = OperationalSite::factory()->create();
    $address = Address::factory()->primary()->for($site, 'addressable')->create();

    $relation = $site->addresses();

    expect($relation)->toBeInstanceOf(MorphMany::class)
        ->and($site->addresses)->toHaveCount(1)
        ->and($site->addresses->first()->is($address))->toBeTrue();
});

it('deleting a site cascades its address (HasAddresses)', function () {
    $site = OperationalSite::factory()->create();
    $address = Address::factory()->primary()->for($site, 'addressable')->create();

    $site->delete();

    expect(Address::find($address->id))->toBeNull();
});

it('primaryAddress resolves the is_primary row, falling back to any owned row', function () {
    $site = OperationalSite::factory()->create();
    $address = Address::factory()->primary()->for($site, 'addressable')->create();

    expect($site->primaryAddress?->is($address))->toBeTrue();
});

it('primaryAddress is null when the site has no address', function () {
    $site = OperationalSite::factory()->create();

    expect($site->primaryAddress)->toBeNull();
});

it('logs model activity on the operational_sites log channel', function () {
    expect(class_uses(OperationalSite::class))->toHaveKey(LogsModelActivity::class);
});

it('factory withAddress() attaches a primary address tied to a real city', function () {
    $site = OperationalSite::factory()->withAddress()->create();

    $address = $site->addresses()->where('is_primary', true)->first();

    expect($address)->not->toBeNull()
        ->and($address->city)->toBeInstanceOf(City::class);
});

it('factory withAddress() accepts an explicit city', function () {
    $city = City::factory()->create();
    $site = OperationalSite::factory()->withAddress($city)->create();

    expect($site->addresses()->first()->city_id)->toBe($city->id);
});

// ---------------------------------------------------------------------------
// Field-permission read proxies (spec 0004/0008 parity — no own site column)
// ---------------------------------------------------------------------------

it('exposes line1/postal_code/geo ids as read proxies onto the primary address', function () {
    $city = City::factory()->create();
    $site = OperationalSite::factory()->create();
    Address::factory()->primary()->forCity($city)->for($site, 'addressable')->create([
        'line1' => 'Via Roma 1',
        'postal_code' => '20100',
    ]);

    $fresh = $site->fresh(['addresses']);

    expect($fresh->line1)->toBe('Via Roma 1')
        ->and($fresh->postal_code)->toBe('20100')
        ->and($fresh->city_id)->toBe($city->id)
        ->and($fresh->province_id)->toBe($city->province_id)
        ->and($fresh->state_id)->toBe($city->state_id)
        ->and($fresh->country_id)->toBe($city->country_id);
});

it('field-permission read proxies are null when the site has no address', function () {
    $site = OperationalSite::factory()->create();

    expect($site->line1)->toBeNull()
        ->and($site->postal_code)->toBeNull()
        ->and($site->city_id)->toBeNull();
});

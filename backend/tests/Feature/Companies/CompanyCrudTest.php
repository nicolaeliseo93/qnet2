<?php

use App\Models\Address;
use App\Models\City;
use App\Models\Company;
use App\Models\Country;
use App\Models\Province;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('userWithCompanyAbilities')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function userWithCompanyAbilities(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("companies.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("companies.{$ability}");
        }

        return $user;
    }
}

/**
 * A real geo chain (country -> state/region -> province -> city) so tests can
 * assert both the ids AND the resolved names on the address block.
 *
 * @return array{country: Country, state: State, province: Province, city: City}
 */
function companyGeoChain(): array
{
    $country = Country::factory()->create(['name' => 'Italia']);
    $state = State::factory()->create(['name' => 'Lombardia', 'country_id' => $country->id]);
    $province = Province::factory()->create(['name' => 'Milano', 'state_id' => $state->id, 'country_id' => $country->id]);
    $city = City::factory()->create(['name' => 'Milano', 'province_id' => $province->id, 'state_id' => $state->id, 'country_id' => $country->id]);

    return compact('country', 'state', 'province', 'city');
}

// ---------------------------------------------------------------------------
// show — GET /api/companies/{company} (AC-003)
// ---------------------------------------------------------------------------

it('show: 200 with the full data shape, address ids AND names', function () {
    $actor = userWithCompanyAbilities(['view']);
    $geo = companyGeoChain();
    $target = Company::factory()->create(['denomination' => 'Acme Srl', 'vat_number' => 'IT12345678901']);
    Address::factory()->primary()->forCity($geo['city'])->for($target, 'addressable')->create([
        'line1' => 'Via Roma 1',
        'postal_code' => '20100',
    ]);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/companies/{$target->id}")
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.id', $target->id)
        ->assertJsonPath('data.denomination', 'Acme Srl')
        ->assertJsonPath('data.vat_number', 'IT12345678901')
        ->assertJsonPath('data.address.line1', 'Via Roma 1')
        ->assertJsonPath('data.address.postal_code', '20100')
        ->assertJsonPath('data.address.city_id', $geo['city']->id)
        ->assertJsonPath('data.address.city', 'Milano')
        ->assertJsonPath('data.address.province', 'Milano')
        ->assertJsonPath('data.address.region', 'Lombardia')
        ->assertJsonPath('data.address.country', 'Italia')
        ->assertJsonPath('data.address.is_primary', true);

    expect($response->json('permissions'))->toHaveKeys(['resource', 'fields', 'actions']);
});

it('show: address is null when the company has none', function () {
    $actor = userWithCompanyAbilities(['view']);
    $target = Company::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/companies/{$target->id}")
        ->assertOk()
        ->assertJsonPath('data.address', null);
});

it('show: 403 without companies.view', function () {
    $actor = userWithCompanyAbilities([]);
    $target = Company::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/companies/{$target->id}")->assertForbidden();
});

it('show: 404 for a non-existent company', function () {
    $actor = userWithCompanyAbilities(['view']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/companies/999999')->assertNotFound();
});

// ---------------------------------------------------------------------------
// create — POST /api/companies (AC-004)
// ---------------------------------------------------------------------------

it('create: 201 + persists the company with a single primary address', function () {
    $actor = userWithCompanyAbilities(['create']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/companies', [
        'denomination' => 'Beta Spa',
        'vat_number' => 'IT99988877769',
        'address' => ['line1' => 'Via Milano 5', 'postal_code' => '10100'],
    ])->assertCreated()
        ->assertJsonPath('data.denomination', 'Beta Spa')
        ->assertJsonPath('data.address.line1', 'Via Milano 5')
        ->assertJsonPath('data.address.is_primary', true);

    // Stored canonical (user directive 2026-07-23): the optional IT prefix is dropped.
    $this->assertDatabaseHas('companies', ['denomination' => 'Beta Spa', 'vat_number' => '99988877769']);
    $this->assertDatabaseHas('addresses', [
        'addressable_type' => 'company',
        'addressable_id' => $response->json('data.id'),
        'line1' => 'Via Milano 5',
        'is_primary' => true,
    ]);
});

it('create: 201 without an address', function () {
    $actor = userWithCompanyAbilities(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/companies', ['denomination' => 'No Address Srl'])
        ->assertCreated()
        ->assertJsonPath('data.address', null);
});

it('create: vat_number is nullable', function () {
    $actor = userWithCompanyAbilities(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/companies', ['denomination' => 'No Vat Srl'])
        ->assertCreated()
        ->assertJsonPath('data.vat_number', null);
});

it('create: 422 when denomination is missing', function () {
    $actor = userWithCompanyAbilities(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/companies', [])
        ->assertStatus(422)->assertJsonValidationErrors('denomination');
});

it('create: 422 when address is present but line1 is missing', function () {
    $actor = userWithCompanyAbilities(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/companies', ['denomination' => 'X', 'address' => ['postal_code' => '00100']])
        ->assertStatus(422)->assertJsonValidationErrors('address.line1');
});

it('create: 422 when a geo id does not exist', function () {
    $actor = userWithCompanyAbilities(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/companies', [
        'denomination' => 'X',
        'address' => ['line1' => 'Via X', 'city_id' => 999999],
    ])->assertStatus(422)->assertJsonValidationErrors('address.city_id');
});

it('create: 403 without companies.create', function () {
    $actor = userWithCompanyAbilities([]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/companies', ['denomination' => 'Nope'])->assertForbidden();
});

// ---------------------------------------------------------------------------
// update — PUT/PATCH /api/companies/{company} (AC-005)
// ---------------------------------------------------------------------------

it('update: PATCH partial (only denomination) leaves vat_number and address untouched', function () {
    $actor = userWithCompanyAbilities(['update']);
    $target = Company::factory()->create(['denomination' => 'Old Name', 'vat_number' => 'IT111']);
    $address = Address::factory()->primary()->for($target, 'addressable')->create(['line1' => 'Original Street']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/companies/{$target->id}", ['denomination' => 'New Name'])
        ->assertOk()
        ->assertJsonPath('data.denomination', 'New Name')
        ->assertJsonPath('data.vat_number', 'IT111')
        ->assertJsonPath('data.address.line1', 'Original Street');

    expect($target->addresses()->count())->toBe(1)
        ->and($address->fresh()->line1)->toBe('Original Street');
});

it('update: PATCH with address updates the EXISTING address, never duplicates it', function () {
    $actor = userWithCompanyAbilities(['update']);
    $target = Company::factory()->create();
    $address = Address::factory()->primary()->for($target, 'addressable')->create(['line1' => 'Before']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/companies/{$target->id}", [
        'address' => ['line1' => 'After'],
    ])->assertOk()->assertJsonPath('data.address.line1', 'After');

    expect($target->addresses()->count())->toBe(1)
        ->and($address->fresh()->line1)->toBe('After');
});

it('update: PATCH with address creates one when the company had none', function () {
    $actor = userWithCompanyAbilities(['update']);
    $target = Company::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/companies/{$target->id}", [
        'address' => ['line1' => 'Brand New Street'],
    ])->assertOk()
        ->assertJsonPath('data.address.line1', 'Brand New Street')
        ->assertJsonPath('data.address.is_primary', true);

    expect($target->addresses()->count())->toBe(1);
});

it('update: vat_number can be cleared to null', function () {
    $actor = userWithCompanyAbilities(['update']);
    $target = Company::factory()->create(['vat_number' => 'IT111']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/companies/{$target->id}", ['vat_number' => null])
        ->assertOk()->assertJsonPath('data.vat_number', null);
});

it('update: 403 without companies.update', function () {
    $actor = userWithCompanyAbilities([]);
    $target = Company::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/companies/{$target->id}", ['denomination' => 'Nope'])->assertForbidden();
});

it('update: 404 for a non-existent company', function () {
    $actor = userWithCompanyAbilities(['update']);
    Sanctum::actingAs($actor);

    $this->patchJson('/api/companies/999999', ['denomination' => 'Ghost'])->assertNotFound();
});

// ---------------------------------------------------------------------------
// delete — DELETE /api/companies/{company} (AC-006)
// ---------------------------------------------------------------------------

it('delete: 204, removes the company and cascades its address', function () {
    $actor = userWithCompanyAbilities(['delete']);
    $target = Company::factory()->create();
    $address = Address::factory()->primary()->for($target, 'addressable')->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/companies/{$target->id}")->assertNoContent();

    $this->assertDatabaseMissing('companies', ['id' => $target->id]);
    $this->assertDatabaseMissing('addresses', ['id' => $address->id]);
});

it('delete: 403 without companies.delete', function () {
    $actor = userWithCompanyAbilities([]);
    $target = Company::factory()->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/companies/{$target->id}")->assertForbidden();
});

it('delete: 404 for a non-existent company', function () {
    $actor = userWithCompanyAbilities(['delete']);
    Sanctum::actingAs($actor);

    $this->deleteJson('/api/companies/999999')->assertNotFound();
});

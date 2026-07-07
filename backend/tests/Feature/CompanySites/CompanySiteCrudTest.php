<?php

use App\Models\Address;
use App\Models\City;
use App\Models\Company;
use App\Models\CompanySite;
use App\Models\CompanySiteBank;
use App\Models\Country;
use App\Models\Province;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('userWithCompanySiteAbilities')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function userWithCompanySiteAbilities(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("company-sites.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("company-sites.{$ability}");
        }

        return $user;
    }
}

if (! function_exists('companySiteGeoChain')) {
    /**
     * @return array{country: Country, state: State, province: Province, city: City}
     */
    function companySiteGeoChain(): array
    {
        $country = Country::factory()->create(['name' => 'Italia']);
        $state = State::factory()->create(['name' => 'Lombardia', 'country_id' => $country->id]);
        $province = Province::factory()->create(['name' => 'Milano', 'state_id' => $state->id, 'country_id' => $country->id]);
        $city = City::factory()->create(['name' => 'Milano', 'province_id' => $province->id, 'state_id' => $state->id, 'country_id' => $country->id]);

        return compact('country', 'state', 'province', 'city');
    }
}

// ---------------------------------------------------------------------------
// show — GET /api/company-sites/{companySite} (AC-006 shape reference)
// ---------------------------------------------------------------------------

it('show: 200 with the full data shape, address + banks + permissions', function () {
    $actor = userWithCompanySiteAbilities(['view']);
    $geo = companySiteGeoChain();
    $target = CompanySite::factory()->create(['name' => 'Sede Nord', 'email' => 'nord@acme.test']);
    Address::factory()->primary()->forCity($geo['city'])->for($target, 'addressable')->create(['line1' => 'Via Roma 1']);
    $bank = CompanySiteBank::factory()->for($target)->create(['name' => 'Banca Uno']);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/company-sites/{$target->id}")
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.id', $target->id)
        ->assertJsonPath('data.name', 'Sede Nord')
        ->assertJsonPath('data.email', 'nord@acme.test')
        ->assertJsonPath('data.is_default', false)
        ->assertJsonPath('data.address.line1', 'Via Roma 1')
        ->assertJsonPath('data.address.city', 'Milano')
        ->assertJsonPath('data.banks.0.name', 'Banca Uno');

    expect($response->json('permissions'))->toHaveKeys(['resource', 'fields', 'actions']);
});

it('show: 403 without company-sites.view', function () {
    $actor = userWithCompanySiteAbilities([]);
    $target = CompanySite::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/company-sites/{$target->id}")->assertForbidden();
});

it('show: 404 for a non-existent site', function () {
    $actor = userWithCompanySiteAbilities(['view']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/company-sites/999999')->assertNotFound();
});

// ---------------------------------------------------------------------------
// create — POST /api/company-sites (AC-007)
// ---------------------------------------------------------------------------

it('create: 201 + persists the site with address and banks, default_bank_id resolved', function () {
    $actor = userWithCompanySiteAbilities(['create']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/company-sites', [
        'name' => 'Sede Sud',
        'email' => 'sud@acme.test',
        'address' => ['line1' => 'Via Napoli 5'],
        'banks' => [['name' => 'Banca Uno', 'iban' => 'IT60X0542811101000000123456']],
    ])->assertCreated()
        ->assertJsonPath('data.name', 'Sede Sud')
        ->assertJsonPath('data.address.line1', 'Via Napoli 5')
        ->assertJsonPath('data.banks.0.name', 'Banca Uno');

    $this->assertDatabaseHas('company_sites', ['name' => 'Sede Sud', 'email' => 'sud@acme.test']);
    $this->assertDatabaseHas('company_site_banks', ['name' => 'Banca Uno']);
});

it('create: 201 without address/banks', function () {
    $actor = userWithCompanySiteAbilities(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/company-sites', ['name' => 'No Frills', 'email' => 'nf@acme.test'])
        ->assertCreated()
        ->assertJsonPath('data.address', null)
        ->assertJsonPath('data.banks', []);
});

it('create: 422 when name/email are missing', function () {
    $actor = userWithCompanySiteAbilities(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/company-sites', [])
        ->assertStatus(422)->assertJsonValidationErrors(['name', 'email']);
});

it('create: 422 when a geo/user id does not exist', function () {
    $actor = userWithCompanySiteAbilities(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/company-sites', [
        'name' => 'X', 'email' => 'x@acme.test',
        'address' => ['line1' => 'Via X', 'city_id' => 999999],
        'responsible_rda_id' => 999999,
    ])->assertStatus(422)->assertJsonValidationErrors(['address.city_id', 'responsible_rda_id']);
});

it('create: 422 on an invalid IBAN', function () {
    $actor = userWithCompanySiteAbilities(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/company-sites', [
        'name' => 'X', 'email' => 'x@acme.test',
        'banks' => [['name' => 'Bad Bank', 'iban' => 'not-an-iban']],
    ])->assertStatus(422)->assertJsonValidationErrors('banks.0.iban');
});

it('create: 422 when default_bank_id is not among the submitted banks', function () {
    $actor = userWithCompanySiteAbilities(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/company-sites', [
        'name' => 'X', 'email' => 'x@acme.test',
        'banks' => [['name' => 'Banca Uno']],
        'default_bank_id' => 123456,
    ])->assertStatus(422)->assertJsonValidationErrors('default_bank_id');
});

it('create: 403 without company-sites.create', function () {
    $actor = userWithCompanySiteAbilities([]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/company-sites', ['name' => 'Nope', 'email' => 'nope@acme.test'])->assertForbidden();
});

it('create: 403 (base authorization) takes precedence over the "Altro" 422', function () {
    $actor = userWithCompanySiteAbilities([]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/company-sites', ['name' => 'X', 'email' => 'x@acme.test', 'company_type' => 2])
        ->assertForbidden();
});

// ---------------------------------------------------------------------------
// delete — DELETE /api/company-sites/{companySite} (AC-012)
// ---------------------------------------------------------------------------

it('delete: 204, removes the site and cascades address + banks', function () {
    $actor = userWithCompanySiteAbilities(['delete']);
    $target = CompanySite::factory()->create();
    $address = Address::factory()->primary()->for($target, 'addressable')->create();
    $bank = CompanySiteBank::factory()->for($target)->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/company-sites/{$target->id}")->assertNoContent();

    $this->assertDatabaseMissing('company_sites', ['id' => $target->id]);
    $this->assertDatabaseMissing('addresses', ['id' => $address->id]);
    $this->assertDatabaseMissing('company_site_banks', ['id' => $bank->id]);
});

it('delete: 403 without company-sites.delete', function () {
    $actor = userWithCompanySiteAbilities([]);
    $target = CompanySite::factory()->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/company-sites/{$target->id}")->assertForbidden();
});

it('delete: 404 for a non-existent site', function () {
    $actor = userWithCompanySiteAbilities(['delete']);
    Sanctum::actingAs($actor);

    $this->deleteJson('/api/company-sites/999999')->assertNotFound();
});

<?php

use App\Models\Address;
use App\Models\City;
use App\Models\PersonalData;
use App\Models\Province;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('userWithAddressAbilities')) {
    /**
     * A non super-admin actor granted exactly the given `addresses.*` abilities.
     *
     * @param  array<int, string>  $abilities
     */
    function userWithAddressAbilities(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
            Permission::findOrCreate("addresses.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("addresses.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// view — GET /api/addresses/{address}
// ---------------------------------------------------------------------------

it('view: 200 re-exposes the hidden locating fields', function () {
    $actor = userWithAddressAbilities(['view']);
    $address = Address::factory()->create([
        'line1' => '10 Downing Street',
        'latitude' => '51.50334000',
        'longitude' => '-0.12768000',
    ]);
    Sanctum::actingAs($actor);

    $this->getJson("/api/addresses/{$address->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $address->id)
        ->assertJsonPath('data.line1', '10 Downing Street')
        ->assertJsonPath('data.latitude', '51.50334000')
        ->assertJsonPath('data.longitude', '-0.12768000');
});

it('view: 403 without addresses.view', function () {
    $actor = userWithAddressAbilities([]);
    $address = Address::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/addresses/{$address->id}")->assertForbidden();
});

it('view: 404 for a non-existent address', function () {
    $actor = userWithAddressAbilities(['view']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/addresses/999999')->assertNotFound();
});

// ---------------------------------------------------------------------------
// create — POST /api/addresses
// ---------------------------------------------------------------------------

it('create: 201 attaches an address to a personal-data owner', function () {
    $actor = userWithAddressAbilities(['create']);
    $card = PersonalData::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/addresses', [
        'addressable_type' => 'personal_data',
        'addressable_id' => $card->id,
        'line1' => '1 Infinite Loop',
        'postal_code' => '95014',
    ])
        ->assertCreated()
        ->assertJsonPath('data.line1', '1 Infinite Loop')
        ->assertJsonPath('data.addressable_type', 'personal_data')
        ->assertJsonPath('data.addressable_id', $card->id);

    $this->assertDatabaseHas('addresses', [
        'addressable_type' => 'personal_data',
        'addressable_id' => $card->id,
        'line1' => '1 Infinite Loop',
    ]);
});

it('create: 201 persists and re-exposes the full geo chain including province_id', function () {
    $actor = userWithAddressAbilities(['create', 'view']);
    $card = PersonalData::factory()->create();
    $state = State::factory()->create();
    $province = Province::factory()->forState($state)->create();
    $city = City::factory()->forProvince($province)->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/addresses', [
        'addressable_type' => 'personal_data',
        'addressable_id' => $card->id,
        'line1' => 'Via Roma 1',
        'country_id' => $province->country_id,
        'state_id' => $state->id,
        'province_id' => $province->id,
        'city_id' => $city->id,
    ])
        ->assertCreated()
        ->assertJsonPath('data.province_id', $province->id)
        ->assertJsonPath('data.state_id', $state->id)
        ->assertJsonPath('data.city_id', $city->id);

    $this->assertDatabaseHas('addresses', [
        'addressable_id' => $card->id,
        'province_id' => $province->id,
        'state_id' => $state->id,
        'city_id' => $city->id,
    ]);
});

it('create: 422 when province_id does not exist', function () {
    $actor = userWithAddressAbilities(['create']);
    $card = PersonalData::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/addresses', [
        'addressable_type' => 'personal_data',
        'addressable_id' => $card->id,
        'line1' => 'Via Roma 1',
        'province_id' => 999999,
    ])->assertStatus(422)->assertJsonValidationErrors('province_id');
});

it('create: 403 without addresses.create', function () {
    $actor = userWithAddressAbilities([]);
    $card = PersonalData::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/addresses', [
        'addressable_type' => 'personal_data',
        'addressable_id' => $card->id,
        'line1' => '1 Infinite Loop',
    ])->assertForbidden();
});

it('create: 201 exposes is_primary in the contract and auto-primes the first address', function () {
    $actor = userWithAddressAbilities(['create']);
    $card = PersonalData::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/addresses', [
        'addressable_type' => 'personal_data',
        'addressable_id' => $card->id,
        'line1' => '1 Infinite Loop',
    ])
        ->assertCreated()
        ->assertJsonPath('data.is_primary', true);

    $this->assertDatabaseHas('addresses', [
        'addressable_type' => 'personal_data',
        'addressable_id' => $card->id,
        'is_primary' => true,
    ]);
});

it('create: 422 when is_primary is not a boolean', function () {
    $actor = userWithAddressAbilities(['create']);
    $card = PersonalData::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/addresses', [
        'addressable_type' => 'personal_data',
        'addressable_id' => $card->id,
        'line1' => '1 Infinite Loop',
        'is_primary' => 'not-a-bool',
    ])->assertStatus(422)->assertJsonValidationErrors('is_primary');
});

it('create: a second address marked primary demotes the first of the same owner', function () {
    $actor = userWithAddressAbilities(['create']);
    $card = PersonalData::factory()->create();
    $first = Address::factory()->primary()->for($card, 'addressable')->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/addresses', [
        'addressable_type' => 'personal_data',
        'addressable_id' => $card->id,
        'line1' => 'Second Street',
        'is_primary' => true,
    ])->assertCreated()->assertJsonPath('data.is_primary', true);

    expect($first->fresh()->is_primary)->toBeFalse();
});

it('create: 422 when line1 is missing', function () {
    $actor = userWithAddressAbilities(['create']);
    $card = PersonalData::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/addresses', [
        'addressable_type' => 'personal_data',
        'addressable_id' => $card->id,
    ])->assertStatus(422)->assertJsonValidationErrors('line1');
});

it('create: 422 when the owner is missing', function () {
    $actor = userWithAddressAbilities(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/addresses', [
        'line1' => '1 Infinite Loop',
    ])->assertStatus(422)->assertJsonValidationErrors('addressable_type');
});

// ---------------------------------------------------------------------------
// update — PUT/PATCH /api/addresses/{address}
// ---------------------------------------------------------------------------

it('update: 200 replaces the address line', function () {
    $actor = userWithAddressAbilities(['update']);
    $address = Address::factory()->create(['line1' => 'Old Street']);
    Sanctum::actingAs($actor);

    $this->putJson("/api/addresses/{$address->id}", [
        'line1' => 'New Street',
    ])->assertOk()->assertJsonPath('data.line1', 'New Street');

    $this->assertDatabaseHas('addresses', ['id' => $address->id, 'line1' => 'New Street']);
});

it('update: 403 without addresses.update', function () {
    $actor = userWithAddressAbilities([]);
    $address = Address::factory()->create();
    Sanctum::actingAs($actor);

    $this->putJson("/api/addresses/{$address->id}", ['line1' => 'New Street'])->assertForbidden();
});

// ---------------------------------------------------------------------------
// delete — DELETE /api/addresses/{address}
// ---------------------------------------------------------------------------

it('delete: 204 and removes the address', function () {
    $actor = userWithAddressAbilities(['delete']);
    $address = Address::factory()->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/addresses/{$address->id}")->assertNoContent();

    $this->assertDatabaseMissing('addresses', ['id' => $address->id]);
});

it('delete: 403 without addresses.delete', function () {
    $actor = userWithAddressAbilities([]);
    $address = Address::factory()->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/addresses/{$address->id}")->assertForbidden();
});

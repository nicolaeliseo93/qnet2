<?php

use App\Models\Address;
use App\Models\Contact;
use App\Models\PersonalData;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('userWithPersonalDataAbilities')) {
    /**
     * A non super-admin actor granted exactly the given `personal_data.*`
     * abilities.
     *
     * @param  array<int, string>  $abilities
     */
    function userWithPersonalDataAbilities(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
            Permission::findOrCreate("personal_data.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("personal_data.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// index — GET /api/personal-data?personable_type={alias}&personable_id={id}
// ---------------------------------------------------------------------------

it('index: 200 returns the owner card with nested contacts/addresses and re-exposed PII', function () {
    $actor = userWithPersonalDataAbilities(['viewAny']);
    $owner = User::factory()->create();
    $card = PersonalData::factory()->for($owner, 'personable')->create([
        'tax_code' => 'RSSMRA80A01H501U',
    ]);
    Contact::factory()->email()->for($card, 'contactable')->create(['value' => 'ada@example.com']);
    Address::factory()->for($card, 'addressable')->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/personal-data?personable_type=user&personable_id={$owner->id}")
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.id', $card->id)
        // PII hidden on the model is deliberately re-exposed by the resource.
        ->assertJsonPath('data.tax_code', 'RSSMRA80A01H501U')
        ->assertJsonPath('data.contacts.0.value', 'ada@example.com')
        ->assertJsonCount(1, 'data.addresses');
});

it('index: 200 returns data = null when the owner exists but has no card', function () {
    $actor = userWithPersonalDataAbilities(['viewAny']);
    $owner = User::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/personal-data?personable_type=user&personable_id={$owner->id}")
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data', null);
});

it('index: 403 without personal_data.viewAny', function () {
    $actor = userWithPersonalDataAbilities([]);
    $owner = User::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/personal-data?personable_type=user&personable_id={$owner->id}")
        ->assertForbidden();
});

it('index: 422 when personable_type is missing', function () {
    $actor = userWithPersonalDataAbilities(['viewAny']);
    $owner = User::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/personal-data?personable_id={$owner->id}")
        ->assertStatus(422)
        ->assertJsonValidationErrors('personable_type');
});

it('index: 422 for an owner alias outside the allowlist', function () {
    $actor = userWithPersonalDataAbilities(['viewAny']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/personal-data?personable_type=role&personable_id=1')
        ->assertStatus(422)
        ->assertJsonValidationErrors('personable_type');
});

it('index: 422 when the owner does not exist', function () {
    $actor = userWithPersonalDataAbilities(['viewAny']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/personal-data?personable_type=user&personable_id=999999')
        ->assertStatus(422)
        ->assertJsonValidationErrors('personable_id');
});

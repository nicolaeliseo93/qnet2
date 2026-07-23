<?php

use App\Enums\PersonalDataTypeEnum;
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
// view — GET /api/personal-data/{personalData}
// ---------------------------------------------------------------------------

it('view: 200 re-exposes the hidden PII and embeds contacts/addresses', function () {
    $actor = userWithPersonalDataAbilities(['view']);
    $card = PersonalData::factory()->create([
        'tax_code' => 'RSSMRA80A01H501U',
        'vat_number' => '12345678901',
    ]);
    Contact::factory()->email()->for($card, 'contactable')->create(['value' => 'ada@example.com']);
    Sanctum::actingAs($actor);

    $this->getJson("/api/personal-data/{$card->id}")
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.id', $card->id)
        // PII hidden on the model is deliberately re-exposed by the resource.
        ->assertJsonPath('data.tax_code', 'RSSMRA80A01H501U')
        ->assertJsonPath('data.vat_number', '12345678901')
        ->assertJsonPath('data.contacts.0.value', 'ada@example.com');
});

it('view: 403 without personal_data.view', function () {
    $actor = userWithPersonalDataAbilities([]);
    $card = PersonalData::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/personal-data/{$card->id}")->assertForbidden();
});

it('view: 404 for a non-existent card', function () {
    $actor = userWithPersonalDataAbilities(['view']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/personal-data/999999')->assertNotFound();
});

// ---------------------------------------------------------------------------
// create — POST /api/personal-data
// ---------------------------------------------------------------------------

it('create: 201 attaches a card to its polymorphic owner', function () {
    $actor = userWithPersonalDataAbilities(['create']);
    $owner = User::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/personal-data', [
        'personable_type' => 'user',
        'personable_id' => $owner->id,
        'type' => PersonalDataTypeEnum::Individual->value,
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'tax_code' => 'LVLDAA80A01H501V',
    ])
        ->assertCreated()
        ->assertJsonPath('data.full_name', 'Ada Lovelace')
        ->assertJsonPath('data.personable_type', 'user')
        ->assertJsonPath('data.personable_id', $owner->id);

    $this->assertDatabaseHas('personal_data', [
        'personable_type' => 'user',
        'personable_id' => $owner->id,
        'first_name' => 'Ada',
    ]);
});

it('create: 422 when the tax code does not match the submitted names', function () {
    $actor = userWithPersonalDataAbilities(['create']);
    $owner = User::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/personal-data', [
        'personable_type' => 'user',
        'personable_id' => $owner->id,
        'type' => PersonalDataTypeEnum::Individual->value,
        'first_name' => 'Ada',
        'last_name' => 'Byron',
        // A valid code, but Lovelace's — not Byron's.
        'tax_code' => 'LVLDAA80A01H501V',
    ])->assertStatus(422)->assertJsonValidationErrors('tax_code');

    $this->assertDatabaseCount('personal_data', 0);
});

it('create: 422 when the VAT number control digit is wrong', function () {
    $actor = userWithPersonalDataAbilities(['create']);
    $owner = User::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/personal-data', [
        'personable_type' => 'user',
        'personable_id' => $owner->id,
        'type' => PersonalDataTypeEnum::Company->value,
        'company_name' => 'Acme SpA',
        'vat_number' => '00743110158',
    ])->assertStatus(422)->assertJsonValidationErrors('vat_number');
});

it('create: 403 without personal_data.create', function () {
    $actor = userWithPersonalDataAbilities([]);
    $owner = User::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/personal-data', [
        'personable_type' => 'user',
        'personable_id' => $owner->id,
        'type' => PersonalDataTypeEnum::Individual->value,
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
    ])->assertForbidden();
});

it('create: 422 when the owner is missing', function () {
    $actor = userWithPersonalDataAbilities(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/personal-data', [
        'type' => PersonalDataTypeEnum::Individual->value,
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
    ])->assertStatus(422)->assertJsonValidationErrors('personable_type');
});

it('create: 422 for an owner alias outside the allowlist', function () {
    $actor = userWithPersonalDataAbilities(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/personal-data', [
        'personable_type' => 'role',
        'personable_id' => 1,
        'type' => PersonalDataTypeEnum::Individual->value,
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
    ])->assertStatus(422)->assertJsonValidationErrors('personable_type');
});

it('create: 422 when the owner does not exist', function () {
    $actor = userWithPersonalDataAbilities(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/personal-data', [
        'personable_type' => 'user',
        'personable_id' => 999999,
        'type' => PersonalDataTypeEnum::Individual->value,
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
    ])->assertStatus(422)->assertJsonValidationErrors('personable_id');
});

it('create: 422 enforces the one-card-per-owner invariant', function () {
    $actor = userWithPersonalDataAbilities(['create']);
    $owner = User::factory()->create();
    PersonalData::factory()->for($owner, 'personable')->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/personal-data', [
        'personable_type' => 'user',
        'personable_id' => $owner->id,
        'type' => PersonalDataTypeEnum::Individual->value,
        'first_name' => 'Grace',
        'last_name' => 'Hopper',
    ])->assertStatus(422)->assertJsonValidationErrors('personable_id');
});

it('create: 422 requires first/last name for an individual', function () {
    $actor = userWithPersonalDataAbilities(['create']);
    $owner = User::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/personal-data', [
        'personable_type' => 'user',
        'personable_id' => $owner->id,
        'type' => PersonalDataTypeEnum::Individual->value,
    ])->assertStatus(422)->assertJsonValidationErrors(['first_name', 'last_name']);
});

// ---------------------------------------------------------------------------
// update — PUT/PATCH /api/personal-data/{personalData}
// ---------------------------------------------------------------------------

it('update: 200 replaces the card attributes', function () {
    $actor = userWithPersonalDataAbilities(['update']);
    $card = PersonalData::factory()->create(['first_name' => 'Before', 'last_name' => 'Name']);
    Sanctum::actingAs($actor);

    $this->putJson("/api/personal-data/{$card->id}", [
        'type' => PersonalDataTypeEnum::Individual->value,
        'first_name' => 'After',
        'last_name' => 'Name',
    ])->assertOk()->assertJsonPath('data.first_name', 'After');

    $this->assertDatabaseHas('personal_data', ['id' => $card->id, 'first_name' => 'After']);
});

it('update: 403 without personal_data.update', function () {
    $actor = userWithPersonalDataAbilities([]);
    $card = PersonalData::factory()->create();
    Sanctum::actingAs($actor);

    $this->putJson("/api/personal-data/{$card->id}", [
        'type' => PersonalDataTypeEnum::Individual->value,
        'first_name' => 'Nope',
        'last_name' => 'Nope',
    ])->assertForbidden();
});

// ---------------------------------------------------------------------------
// delete — DELETE /api/personal-data/{personalData}
// ---------------------------------------------------------------------------

it('delete: 204 and cascades its contacts away', function () {
    $actor = userWithPersonalDataAbilities(['delete']);
    $card = PersonalData::factory()->create();
    $contact = Contact::factory()->for($card, 'contactable')->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/personal-data/{$card->id}")->assertNoContent();

    $this->assertDatabaseMissing('personal_data', ['id' => $card->id]);
    $this->assertDatabaseMissing('contacts', ['id' => $contact->id]);
});

it('delete: 403 without personal_data.delete', function () {
    $actor = userWithPersonalDataAbilities([]);
    $card = PersonalData::factory()->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/personal-data/{$card->id}")->assertForbidden();
});

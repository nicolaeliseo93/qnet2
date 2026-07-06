<?php

use App\Models\Contact;
use App\Models\PersonalData;
use App\Models\Referent;
use App\Models\ReferentType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('referentUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function referentUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("referents.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("referents.{$ability}");
        }

        return $user;
    }
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
if (! function_exists('minimalReferentProfilePayload')) {
    function minimalReferentProfilePayload(array $overrides = []): array
    {
        return array_merge([
            'type' => 'individual',
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
        ], $overrides);
    }
}

// ---------------------------------------------------------------------------
// update — PUT/PATCH /api/referents/{referent} (AC-012)
// ---------------------------------------------------------------------------

it('update: PATCH partial (only personal_data.contacts) full-replaces contacts, other fields untouched', function () {
    $actor = referentUserWith(['update']);
    $target = Referent::factory()->create(['contact_scope' => 'internal', 'notes' => 'Keep me']);
    $card = PersonalData::factory()->for($target, 'personable')->create(['first_name' => 'Ada', 'last_name' => 'Lovelace']);
    $oldContact = Contact::factory()->email()->for($card, 'contactable')->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/referents/{$target->id}", [
        'personal_data' => [
            'type' => 'individual',
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'contacts' => [['type' => 'phone', 'value' => '+39 06 1234567']],
        ],
    ])->assertOk()->assertJsonPath('data.notes', 'Keep me');

    $this->assertDatabaseMissing('contacts', ['id' => $oldContact->id]);
    expect($card->contacts()->where('value', '+39 06 1234567')->exists())->toBeTrue();
});

it('update: PATCH {referent_type_id: null} removes the type', function () {
    $actor = referentUserWith(['update']);
    $type = ReferentType::factory()->create();
    $target = Referent::factory()->create(['referent_type_id' => $type->id]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/referents/{$target->id}", ['referent_type_id' => null])
        ->assertOk()
        ->assertJsonPath('data.referent_type_id', null)
        ->assertJsonPath('data.referent_type', null);

    $this->assertDatabaseHas('referents', ['id' => $target->id, 'referent_type_id' => null]);
});

it('update: changing the card re-derives name', function () {
    $actor = referentUserWith(['update']);
    $target = Referent::factory()->create(['name' => 'Old Name']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/referents/{$target->id}", [
        'personal_data' => minimalReferentProfilePayload(['first_name' => 'Grace', 'last_name' => 'Hopper']),
    ])->assertOk()->assertJsonPath('data.name', 'Grace Hopper');

    $this->assertDatabaseHas('referents', ['id' => $target->id, 'name' => 'Grace Hopper']);
});

it('update: 422 when the submitted contact_scope is invalid', function () {
    $actor = referentUserWith(['update']);
    $target = Referent::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/referents/{$target->id}", ['contact_scope' => 'bogus'])
        ->assertStatus(422)->assertJsonValidationErrors('contact_scope');
});

it('update: 403 without referents.update', function () {
    $actor = referentUserWith([]);
    $target = Referent::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/referents/{$target->id}", ['notes' => 'Nope'])->assertForbidden();
});

it('update: 404 for a non-existent referent', function () {
    $actor = referentUserWith(['update']);
    Sanctum::actingAs($actor);

    $this->patchJson('/api/referents/999999', ['notes' => 'Ghost'])->assertNotFound();
});

// ---------------------------------------------------------------------------
// delete — DELETE /api/referents/{referent} (AC-014)
// ---------------------------------------------------------------------------

it('delete: 204, removes the referent and cascades its personal-data card', function () {
    $actor = referentUserWith(['delete']);
    $target = Referent::factory()->create();
    $card = PersonalData::factory()->for($target, 'personable')->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/referents/{$target->id}")->assertNoContent();

    $this->assertDatabaseMissing('referents', ['id' => $target->id]);
    $this->assertDatabaseMissing('personal_data', ['id' => $card->id]);
});

it('delete: 403 without referents.delete', function () {
    $actor = referentUserWith([]);
    $target = Referent::factory()->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/referents/{$target->id}")->assertForbidden();
});

it('delete: 404 for a non-existent referent', function () {
    $actor = referentUserWith(['delete']);
    Sanctum::actingAs($actor);

    $this->deleteJson('/api/referents/999999')->assertNotFound();
});

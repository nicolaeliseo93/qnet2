<?php

use App\Models\Contact;
use App\Models\PersonalData;
use App\Models\Referent;
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

// ---------------------------------------------------------------------------
// keeps an existing child by id and deletes the omitted one (full-replace sync)
// ---------------------------------------------------------------------------

it('update: keeps an existing contact by id and deletes the omitted one', function () {
    $actor = referentUserWith(['update']);
    $target = Referent::factory()->create();
    $card = PersonalData::factory()->for($target, 'personable')->create();
    $keep = Contact::factory()->email()->for($card, 'contactable')->create();
    $drop = Contact::factory()->email()->for($card, 'contactable')->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/referents/{$target->id}", [
        'personal_data' => [
            'type' => 'individual',
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'contacts' => [
                ['id' => $keep->id, 'type' => 'email', 'value' => 'updated@example.com'],
            ],
        ],
    ])->assertOk();

    $this->assertDatabaseHas('contacts', ['id' => $keep->id, 'value' => 'updated@example.com']);
    $this->assertDatabaseMissing('contacts', ['id' => $drop->id]);
});

it('update: absent collection key leaves that collection untouched', function () {
    $actor = referentUserWith(['update']);
    $target = Referent::factory()->create();
    $card = PersonalData::factory()->for($target, 'personable')->create();
    $contact = Contact::factory()->email()->for($card, 'contactable')->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/referents/{$target->id}", [
        'personal_data' => [
            'type' => 'individual',
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'addresses' => [['line1' => 'New St']],
        ],
    ])->assertOk();

    $this->assertDatabaseHas('contacts', ['id' => $contact->id]);
    expect($card->addresses()->count())->toBe(1);
});

it('update: empty array deletes all owned contacts', function () {
    $actor = referentUserWith(['update']);
    $target = Referent::factory()->create();
    $card = PersonalData::factory()->for($target, 'personable')->create();
    Contact::factory()->email()->for($card, 'contactable')->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/referents/{$target->id}", [
        'personal_data' => [
            'type' => 'individual',
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'contacts' => [],
        ],
    ])->assertOk();

    expect($card->contacts()->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// cross-card id is treated as create, never mutates another card's child
// ---------------------------------------------------------------------------

it('update: a cross-card contact id is treated as create, not an update of the other card', function () {
    $actor = referentUserWith(['update']);

    $other = Referent::factory()->create();
    $otherCard = PersonalData::factory()->for($other, 'personable')->create();
    $foreign = Contact::factory()->email()->for($otherCard, 'contactable')->create(['value' => 'foreign@example.com']);

    $target = Referent::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/referents/{$target->id}", [
        'personal_data' => [
            'type' => 'individual',
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'contacts' => [
                ['id' => $foreign->id, 'type' => 'email', 'value' => 'mine@example.com'],
            ],
        ],
    ])->assertOk();

    $this->assertDatabaseHas('contacts', ['id' => $foreign->id, 'value' => 'foreign@example.com']);
    $targetCard = $target->personalData()->firstOrFail();
    expect($targetCard->contacts()->where('value', 'mine@example.com')->exists())->toBeTrue()
        ->and($foreign->fresh()->contactable_id)->toBe($otherCard->id);
});

// ---------------------------------------------------------------------------
// single-primary invariants after a batch
// ---------------------------------------------------------------------------

it('update: keeps one primary contact per type after a batch', function () {
    $actor = referentUserWith(['update']);
    $target = Referent::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/referents/{$target->id}", [
        'personal_data' => [
            'type' => 'individual',
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'contacts' => [
                ['type' => 'email', 'value' => 'a@example.com', 'is_primary' => true],
                ['type' => 'email', 'value' => 'b@example.com', 'is_primary' => true],
            ],
        ],
    ])->assertOk();

    $card = $target->personalData()->firstOrFail();
    expect($card->contacts()->where('type', 'email')->where('is_primary', true)->count())->toBe(1);
});

// ---------------------------------------------------------------------------
// authorization
// ---------------------------------------------------------------------------

it('create: 403 without referents.create even with a profile', function () {
    $actor = referentUserWith([]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/referents', [
        'contact_scope' => 'internal',
        'personal_data' => ['type' => 'individual', 'first_name' => 'Ada', 'last_name' => 'Lovelace'],
    ])->assertForbidden();

    expect(Referent::count())->toBe(0);
});

it('update: 403 without referents.update even with a profile', function () {
    $actor = referentUserWith([]);
    $target = Referent::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/referents/{$target->id}", [
        'personal_data' => ['type' => 'individual', 'first_name' => 'Ada', 'last_name' => 'Lovelace'],
    ])->assertForbidden();

    expect($target->personalData()->exists())->toBeFalse();
});

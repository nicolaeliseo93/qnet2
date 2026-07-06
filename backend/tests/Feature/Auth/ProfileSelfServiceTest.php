<?php

use App\Models\Address;
use App\Models\Contact;
use App\Models\PersonalData;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

/**
 * Self-service profile management on /api/auth/me (ADR 0013). The nested
 * personal_data contract mirrors PATCH /api/users/{user} (ADR 0012) verbatim:
 * the owner is always the authenticated user (ownership by construction), so no
 * Spatie permission is required and any personable_* in the input is ignored.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function selfProfilePayload(array $overrides = []): array
{
    return array_merge([
        'type' => 'individual',
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'tax_code' => 'LVLADA90A01H501Z',
        'birth_date' => '1990-01-01',
        'contacts' => [
            ['type' => 'email', 'value' => 'ada@example.com', 'label' => 'Work', 'is_primary' => true],
        ],
        'addresses' => [
            ['line1' => '10 Analytical St', 'postal_code' => '00100', 'is_primary' => true],
        ],
    ], $overrides);
}

// ---------------------------------------------------------------------------
// GET /auth/me exposes the personal_data tree
// ---------------------------------------------------------------------------

it('GET /auth/me exposes personal_data when the card exists', function () {
    $user = User::factory()->create();
    $card = PersonalData::factory()->for($user, 'personable')->create(['first_name' => 'Ada', 'last_name' => 'Lovelace']);
    Contact::factory()->email()->for($card, 'contactable')->create(['value' => 'ada@example.com']);
    Address::factory()->for($card, 'addressable')->create(['line1' => '10 Analytical St']);
    Sanctum::actingAs($user);

    $this->getJson('/api/auth/me')
        ->assertOk()
        ->assertJsonPath('data.personal_data.first_name', 'Ada')
        ->assertJsonPath('data.personal_data.contacts.0.value', 'ada@example.com')
        ->assertJsonPath('data.personal_data.addresses.0.line1', '10 Analytical St');
});

it('GET /auth/me omits personal_data when no card exists', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->getJson('/api/auth/me')
        ->assertOk()
        ->assertJsonMissingPath('data.personal_data');
});

// ---------------------------------------------------------------------------
// PATCH /auth/me creates/updates the card + derives users.name
// ---------------------------------------------------------------------------

it('PATCH /auth/me creates the card and derives users.name from it', function () {
    $user = User::factory()->create(['name' => 'Old Name', 'locale' => 'en']);
    Sanctum::actingAs($user);

    $this->patchJson('/api/auth/me', [
        'locale' => 'it',
        'personal_data' => selfProfilePayload(),
    ])->assertOk()
        ->assertJsonPath('data.name', 'Ada Lovelace')
        ->assertJsonPath('data.locale', 'it')
        ->assertJsonPath('data.personal_data.contacts.0.value', 'ada@example.com');

    $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Ada Lovelace', 'locale' => 'it']);

    $card = $user->personalData()->firstOrFail();
    expect($card->contacts()->count())->toBe(1)
        ->and($card->addresses()->count())->toBe(1)
        ->and($card->addresses()->first()->is_primary)->toBeTrue();

    $this->assertDatabaseHas('personal_data', [
        'id' => $card->id,
        'personable_type' => 'user',
        'personable_id' => $user->id,
    ]);
});

it('PATCH /auth/me updates an existing card', function () {
    $user = User::factory()->create();
    PersonalData::factory()->for($user, 'personable')->create(['first_name' => 'Old', 'last_name' => 'Name']);
    Sanctum::actingAs($user);

    $this->patchJson('/api/auth/me', [
        'personal_data' => ['type' => 'individual', 'first_name' => 'Grace', 'last_name' => 'Hopper'],
    ])->assertOk()
        ->assertJsonPath('data.name', 'Grace Hopper');

    expect($user->personalData()->count())->toBe(1);
    $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Grace Hopper']);
});

// ---------------------------------------------------------------------------
// authoritative sync of contacts/addresses
// ---------------------------------------------------------------------------

it('PATCH /auth/me keeps a child by id and deletes the omitted one', function () {
    $user = User::factory()->create();
    $card = PersonalData::factory()->for($user, 'personable')->create();
    $keep = Contact::factory()->email()->for($card, 'contactable')->create();
    $drop = Contact::factory()->email()->for($card, 'contactable')->create();
    Sanctum::actingAs($user);

    $this->patchJson('/api/auth/me', [
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

it('PATCH /auth/me with an empty collection deletes all owned children', function () {
    $user = User::factory()->create();
    $card = PersonalData::factory()->for($user, 'personable')->create();
    Contact::factory()->email()->for($card, 'contactable')->create();
    Contact::factory()->for($card, 'contactable')->create();
    Sanctum::actingAs($user);

    $this->patchJson('/api/auth/me', [
        'personal_data' => ['type' => 'individual', 'first_name' => 'Ada', 'last_name' => 'Lovelace', 'contacts' => []],
    ])->assertOk();

    expect($card->contacts()->count())->toBe(0);
});

it('PATCH /auth/me leaves an absent collection key untouched', function () {
    $user = User::factory()->create();
    $card = PersonalData::factory()->for($user, 'personable')->create();
    $contact = Contact::factory()->email()->for($card, 'contactable')->create();
    Sanctum::actingAs($user);

    $this->patchJson('/api/auth/me', [
        'personal_data' => [
            'type' => 'individual',
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'addresses' => [['line1' => 'New St']],
        ],
    ])->assertOk();

    $this->assertDatabaseHas('contacts', ['id' => $contact->id]);
});

it('PATCH /auth/me keeps a single primary per type after a batch', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->patchJson('/api/auth/me', [
        'personal_data' => [
            'type' => 'individual',
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'contacts' => [
                ['type' => 'email', 'value' => 'a@example.com', 'is_primary' => true],
                ['type' => 'email', 'value' => 'b@example.com', 'is_primary' => true],
            ],
            'addresses' => [
                ['line1' => 'A', 'is_primary' => true],
                ['line1' => 'B', 'is_primary' => true],
            ],
        ],
    ])->assertOk();

    $card = $user->personalData()->firstOrFail();
    expect($card->contacts()->where('type', 'email')->where('is_primary', true)->count())->toBe(1)
        ->and($card->addresses()->where('is_primary', true)->count())->toBe(1);
});

// ---------------------------------------------------------------------------
// ownership / IDOR: a foreign child id is never reassigned
// ---------------------------------------------------------------------------

it('PATCH /auth/me treats a foreign contact id as create, never mutating the other card', function () {
    $other = User::factory()->create();
    $otherCard = PersonalData::factory()->for($other, 'personable')->create();
    $foreign = Contact::factory()->email()->for($otherCard, 'contactable')->create(['value' => 'foreign@example.com']);

    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->patchJson('/api/auth/me', [
        'personal_data' => [
            'type' => 'individual',
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'contacts' => [
                ['id' => $foreign->id, 'type' => 'email', 'value' => 'mine@example.com'],
            ],
        ],
    ])->assertOk();

    // The foreign contact is untouched and still owned by the other card.
    $this->assertDatabaseHas('contacts', ['id' => $foreign->id, 'value' => 'foreign@example.com']);
    expect($foreign->fresh()->contactable_id)->toBe($otherCard->id);

    // A brand-new contact was created on the actor's own card.
    $myCard = $user->personalData()->firstOrFail();
    expect($myCard->contacts()->where('value', 'mine@example.com')->exists())->toBeTrue();
});

// ---------------------------------------------------------------------------
// forbidden fields: name / roles / password / personable_* never escalate
// ---------------------------------------------------------------------------

it('PATCH /auth/me ignores a client-supplied name (derived from the card)', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->patchJson('/api/auth/me', [
        'name' => 'Hacker Chosen Name',
        'personal_data' => selfProfilePayload(),
    ])->assertOk()
        ->assertJsonPath('data.name', 'Ada Lovelace');

    $this->assertDatabaseHas('users', ['id' => $user->id, 'name' => 'Ada Lovelace']);
    $this->assertDatabaseMissing('users', ['id' => $user->id, 'name' => 'Hacker Chosen Name']);
});

it('PATCH /auth/me ignores roles, password and personable_* in the payload', function () {
    $user = User::factory()->create();
    $originalPassword = $user->password;
    Sanctum::actingAs($user);

    $this->patchJson('/api/auth/me', [
        'roles' => [999],
        'password' => 'attacker-password',
        'password_confirmation' => 'attacker-password',
        'personal_data' => array_merge(selfProfilePayload(), [
            'personable_type' => 'user',
            'personable_id' => 123456,
        ]),
    ])->assertOk();

    // No role escalation, password unchanged.
    expect($user->fresh()->roles()->count())->toBe(0)
        ->and($user->fresh()->password)->toBe($originalPassword);

    // The card is owned by the actor, not by the spoofed personable_id.
    $card = $user->personalData()->firstOrFail();
    $this->assertDatabaseHas('personal_data', [
        'id' => $card->id,
        'personable_type' => 'user',
        'personable_id' => $user->id,
    ]);
});

// ---------------------------------------------------------------------------
// rollback: an invalid nested payload leaves no orphan card/child
// ---------------------------------------------------------------------------

it('PATCH /auth/me rejects a bad contact value for its type', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->patchJson('/api/auth/me', [
        'personal_data' => selfProfilePayload([
            'contacts' => [['type' => 'email', 'value' => 'not-an-email']],
        ]),
    ])->assertStatus(422)
        ->assertJsonValidationErrors('personal_data.contacts.0.value');

    expect($user->personalData()->exists())->toBeFalse();
});

it('PATCH /auth/me requires a type when personal_data is present', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->patchJson('/api/auth/me', [
        'personal_data' => ['first_name' => 'X'],
    ])->assertStatus(422)
        ->assertJsonValidationErrors('personal_data.type');
});

// ---------------------------------------------------------------------------
// account fields: email is read-only (registration email) + locale validation
// ---------------------------------------------------------------------------

it('PATCH /auth/me ignores a submitted email and keeps the registration email', function () {
    $user = User::factory()->create(['email' => 'mine@example.com']);
    Sanctum::actingAs($user);

    $this->patchJson('/api/auth/me', [
        'email' => 'changed@example.com',
    ])->assertOk()
        ->assertJsonPath('data.email', 'mine@example.com');

    $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => 'mine@example.com']);
});

it('PATCH /auth/me ignores an email even if already used by another user (no 422)', function () {
    User::factory()->create(['email' => 'taken@example.com']);
    $user = User::factory()->create(['email' => 'mine@example.com']);
    Sanctum::actingAs($user);

    $this->patchJson('/api/auth/me', [
        'email' => 'taken@example.com',
    ])->assertOk()
        ->assertJsonPath('data.email', 'mine@example.com');

    $this->assertDatabaseHas('users', ['id' => $user->id, 'email' => 'mine@example.com']);
});

it('PATCH /auth/me rejects a locale outside LocaleEnum', function () {
    $user = User::factory()->create(['locale' => 'en']);
    Sanctum::actingAs($user);

    $this->patchJson('/api/auth/me', [
        'locale' => 'fr',
    ])->assertStatus(422)
        ->assertJsonValidationErrors('locale');

    $this->assertDatabaseHas('users', ['id' => $user->id, 'locale' => 'en']);
});

it('PATCH /auth/me treats a foreign address id as create, never mutating the other card', function () {
    $other = User::factory()->create();
    $otherCard = PersonalData::factory()->for($other, 'personable')->create();
    $foreign = Address::factory()->for($otherCard, 'addressable')->create(['line1' => 'Foreign St']);

    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->patchJson('/api/auth/me', [
        'personal_data' => [
            'type' => 'individual',
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'addresses' => [
                ['id' => $foreign->id, 'line1' => 'Mine St'],
            ],
        ],
    ])->assertOk();

    // The foreign address is untouched and still owned by the other card.
    $this->assertDatabaseHas('addresses', ['id' => $foreign->id, 'line1' => 'Foreign St']);
    expect($foreign->fresh()->addressable_id)->toBe($otherCard->id);

    // A brand-new address was created on the actor's own card.
    $myCard = $user->personalData()->firstOrFail();
    expect($myCard->addresses()->where('line1', 'Mine St')->exists())->toBeTrue();
});

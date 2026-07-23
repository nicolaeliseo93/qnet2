<?php

use App\Models\Contact;
use App\Models\PersonalData;
use App\Models\Referent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('referentDuplicateCheckUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function referentDuplicateCheckUserWith(array $abilities): User
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
// AC-001 — email, case-insensitive
// ---------------------------------------------------------------------------

it('AC-001: matches an existing referent by case-insensitive email', function () {
    $actor = referentDuplicateCheckUserWith(['create']);
    $referent = Referent::factory()->create();
    $card = PersonalData::factory()->individual()->for($referent, 'personable')->create();
    Contact::factory()->email()->for($card, 'contactable')->create(['value' => 'Mario.Rossi@Example.com']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/referents/duplicate-check', [
        'contacts' => [['type' => 'email', 'value' => 'mario.rossi@example.com']],
    ])->assertOk();

    expect($response->json('data.matches'))->toHaveCount(1)
        ->and($response->json('data.matches.0'))->toMatchArray([
            'referent_id' => $referent->id,
            'name' => $referent->name,
            'matched_on' => ['email'],
        ]);
});

// ---------------------------------------------------------------------------
// AC-002 — phone, normalized digits regardless of formatting; different
// digits do not match
// ---------------------------------------------------------------------------

it('AC-002: matches a phone regardless of formatting; different digits do not match', function () {
    $actor = referentDuplicateCheckUserWith(['create']);
    $referent = Referent::factory()->create();
    $card = PersonalData::factory()->individual()->for($referent, 'personable')->create();
    Contact::factory()->phone()->for($card, 'contactable')->create(['value' => '+39 02 1234-567']);
    Sanctum::actingAs($actor);

    $match = $this->postJson('/api/referents/duplicate-check', [
        'contacts' => [['type' => 'phone', 'value' => '+3902 1234567']],
    ])->assertOk();

    expect($match->json('data.matches.0.matched_on'))->toBe(['phone']);

    $noMatch = $this->postJson('/api/referents/duplicate-check', [
        'contacts' => [['type' => 'phone', 'value' => '+39 02 1234-000']],
    ])->assertOk();

    expect($noMatch->json('data.matches'))->toBe([]);
});

// ---------------------------------------------------------------------------
// AC-003 — tax_code, case/whitespace-insensitive
// ---------------------------------------------------------------------------

it('AC-003: matches an existing referent by case/whitespace-insensitive tax_code', function () {
    $actor = referentDuplicateCheckUserWith(['create']);
    $referent = Referent::factory()->create();
    PersonalData::factory()->individual()->for($referent, 'personable')->create(['tax_code' => 'rssmra80a01h501u']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/referents/duplicate-check', [
        'tax_code' => 'RSSMRA80A01H501U ',
    ])->assertOk();

    expect($response->json('data.matches.0'))->toMatchArray([
        'referent_id' => $referent->id,
        'matched_on' => ['tax_code'],
    ]);
});

// ---------------------------------------------------------------------------
// AC-004 — cumulative matched_on, max 5 / id desc, non-Referent cards excluded
// ---------------------------------------------------------------------------

it('AC-004: a referent matching on multiple criteria appears once with cumulative matched_on', function () {
    $actor = referentDuplicateCheckUserWith(['create']);
    $referent = Referent::factory()->create();
    $card = PersonalData::factory()->individual()->for($referent, 'personable')->create(['tax_code' => 'LVLDAA80A01H501V']);
    Contact::factory()->email()->for($card, 'contactable')->create(['value' => 'dup@example.com']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/referents/duplicate-check', [
        'tax_code' => 'lvldaa80a01h501v',
        'contacts' => [['type' => 'email', 'value' => 'DUP@example.com']],
    ])->assertOk();

    expect($response->json('data.matches'))->toHaveCount(1)
        ->and($response->json('data.matches.0.matched_on'))->toBe(['email', 'tax_code']);
});

it('AC-004: caps at 5 matches ordered by id desc', function () {
    $actor = referentDuplicateCheckUserWith(['create']);
    $referents = Referent::factory()->count(6)->create();

    foreach ($referents as $referent) {
        $card = PersonalData::factory()->individual()->for($referent, 'personable')->create();
        Contact::factory()->email()->for($card, 'contactable')->create(['value' => 'shared@example.com']);
    }

    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/referents/duplicate-check', [
        'contacts' => [['type' => 'email', 'value' => 'shared@example.com']],
    ])->assertOk();

    $expectedIds = $referents->sortByDesc('id')->take(5)->pluck('id')->values()->all();

    expect($response->json('data.matches'))->toHaveCount(5)
        ->and(collect($response->json('data.matches'))->pluck('referent_id')->all())->toBe($expectedIds);
});

it('AC-004: a contact belonging to a non-Referent PersonalData (e.g. a User) does not match', function () {
    $actor = referentDuplicateCheckUserWith(['create']);
    $user = User::factory()->create();
    $card = PersonalData::factory()->individual()->for($user, 'personable')->create();
    Contact::factory()->email()->for($card, 'contactable')->create(['value' => 'user-only@example.com']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/referents/duplicate-check', [
        'contacts' => [['type' => 'email', 'value' => 'user-only@example.com']],
    ])->assertOk();

    expect($response->json('data.matches'))->toBe([]);
});

// ---------------------------------------------------------------------------
// AC-005 — auth/authz/validation + no PII leak
// ---------------------------------------------------------------------------

it('requires authentication (401)', function () {
    $this->postJson('/api/referents/duplicate-check', ['tax_code' => 'X'])->assertUnauthorized();
});

it('forbids actors without referents.create (403)', function () {
    $actor = referentDuplicateCheckUserWith([]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/referents/duplicate-check', ['tax_code' => 'X'])->assertForbidden();
});

it('rejects a payload with no criteria at all (422)', function () {
    $actor = referentDuplicateCheckUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/referents/duplicate-check', [])->assertStatus(422);
});

it('rejects a payload with only blank criteria (422)', function () {
    $actor = referentDuplicateCheckUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/referents/duplicate-check', [
        'tax_code' => '   ',
        'contacts' => [['type' => 'email', 'value' => '  ']],
    ])->assertStatus(422);
});

it('rejects an unknown contact type (422)', function () {
    $actor = referentDuplicateCheckUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/referents/duplicate-check', [
        'contacts' => [['type' => 'fax', 'value' => '+39021234567']],
    ])->assertStatus(422);
});

it('the response never contains a contact value or tax_code', function () {
    $actor = referentDuplicateCheckUserWith(['create']);
    $referent = Referent::factory()->create();
    $card = PersonalData::factory()->individual()->for($referent, 'personable')->create(['tax_code' => 'RSSMRA80A01H501U']);
    Contact::factory()->email()->for($card, 'contactable')->create(['value' => 'leak@example.com']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/referents/duplicate-check', [
        'tax_code' => 'RSSMRA80A01H501U',
    ])->assertOk();

    $body = $response->getContent();

    expect($body)->not->toContain('leak@example.com')
        ->and($body)->not->toContain('RSSMRA80A01H501U')
        ->and(array_keys($response->json('data.matches.0')))->toEqualCanonicalizing(['referent_id', 'name', 'matched_on']);
});

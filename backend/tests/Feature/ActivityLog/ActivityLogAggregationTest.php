<?php

use App\Enums\GenderEnum;
use App\Models\Address;
use App\Models\Contact;
use App\Models\PersonalData;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * @param  array<int, string>  $abilities
 */
if (! function_exists('activityLogActor')) {
    function activityLogActor(array $abilities): User
    {
        foreach (['viewAny', 'view', 'viewActivity'] as $ability) {
            Permission::findOrCreate("users.{$ability}");
        }

        $actor = User::factory()->create();

        foreach ($abilities as $ability) {
            $actor->givePermissionTo("users.{$ability}");
        }

        return $actor;
    }
}

/**
 * A user with a full personal-data card (one contact, one address), so
 * updates on every aggregated level (personal_data/contact/address) can be
 * exercised against the same root.
 *
 * @return array{user: User, personalData: PersonalData, contact: Contact, address: Address}
 */
if (! function_exists('userWithFullProfile')) {
    function userWithFullProfile(): array
    {
        $user = User::factory()->create();
        // Fixed gender (rather than the factory's random draw) so a test that
        // flips it to the other value is guaranteed a real, dirty change.
        $personalData = PersonalData::factory()->individual()->for($user, 'personable')
            ->create(['gender' => GenderEnum::Male->value]);
        $contact = Contact::factory()->email()->for($personalData, 'contactable')->create();
        $address = Address::factory()->for($personalData, 'addressable')->create();

        return compact('user', 'personalData', 'contact', 'address');
    }
}

// ---------------------------------------------------------------------------
// Provenance across aggregated relations (AC-005, AC-006)
// ---------------------------------------------------------------------------

it('a personal_data change is aggregated with module=personal_data and the right subject_id (AC-005)', function () {
    $actor = activityLogActor(['view', 'viewActivity']);
    $profile = userWithFullProfile();
    Sanctum::actingAs($actor);

    $profile['personalData']->update(['last_name' => 'Changed']);

    $items = $this->getJson("/api/activity-log/users/{$profile['user']->id}")->assertOk()->json('data.items');

    $entry = collect($items)->firstWhere('module', 'personal_data');

    expect($entry)->not->toBeNull()
        ->and($entry['subject_id'])->toBe($profile['personalData']->id);
});

it('a contact/address change is aggregated with module=contact/address (AC-006)', function () {
    $actor = activityLogActor(['view', 'viewActivity']);
    $profile = userWithFullProfile();
    Sanctum::actingAs($actor);

    $profile['contact']->update(['label' => 'Updated Label']);
    $profile['address']->update(['postal_code' => '00199']);

    $items = collect($this->getJson("/api/activity-log/users/{$profile['user']->id}")->assertOk()->json('data.items'));

    $contactEntry = $items->firstWhere('module', 'contact');
    $addressEntry = $items->firstWhere('module', 'address');

    expect($contactEntry)->not->toBeNull()
        ->and($contactEntry['subject_id'])->toBe($profile['contact']->id)
        ->and($addressEntry)->not->toBeNull()
        ->and($addressEntry['subject_id'])->toBe($profile['address']->id);
});

// ---------------------------------------------------------------------------
// changes[] shape (AC-007, AC-008)
// ---------------------------------------------------------------------------

it('one updated entry lists changes[] for every dirty field, old/new coherent with properties (AC-007)', function () {
    $actor = activityLogActor(['view', 'viewActivity']);
    $target = User::factory()->create(['locale' => 'en', 'is_active' => true]);
    Sanctum::actingAs($actor);

    $target->update(['locale' => 'it', 'is_active' => false]);

    $items = collect($this->getJson("/api/activity-log/users/{$target->id}")->assertOk()->json('data.items'));

    $updated = $items->firstWhere('event', 'updated');

    expect($updated)->not->toBeNull()
        ->and($updated['changes'])->toHaveCount(2);

    $byField = collect($updated['changes'])->keyBy('field');

    expect($byField['locale']['old_value'])->toBe('en')
        ->and($byField['locale']['new_value'])->toBe('it')
        ->and($byField['is_active']['old_value'])->toBeTrue()
        ->and($byField['is_active']['new_value'])->toBeFalse();
});

it('a created entry has old_value=null for every field (AC-008)', function () {
    $actor = activityLogActor(['view', 'viewActivity']);
    Sanctum::actingAs($actor);

    $target = User::factory()->create(['locale' => 'en']);

    $items = collect($this->getJson("/api/activity-log/users/{$target->id}")->assertOk()->json('data.items'));

    $created = $items->firstWhere('event', 'created');

    expect($created)->not->toBeNull()
        ->and($created['changes'])->not->toBeEmpty();

    foreach ($created['changes'] as $change) {
        expect($change['old_value'])->toBeNull();
    }
});

// ---------------------------------------------------------------------------
// Hidden fields never leak (AC-011)
// ---------------------------------------------------------------------------

it('hidden fields never appear in changes[] (AC-011)', function () {
    $actor = activityLogActor(['view', 'viewActivity']);
    $profile = userWithFullProfile();
    Sanctum::actingAs($actor);

    // Each update pairs a hidden field with a visible one, so an entry IS
    // logged (dontSubmitEmptyLogs would otherwise suppress a hidden-only diff).
    $profile['personalData']->update(['tax_code' => 'AAAAAA00A00A000A', 'gender' => 'female']);
    $profile['contact']->update(['value' => 'changed@example.com', 'label' => 'New label']);
    $profile['address']->update(['line1' => 'New Street 1', 'postal_code' => '00100']);

    $items = collect($this->getJson("/api/activity-log/users/{$profile['user']->id}")->assertOk()->json('data.items'));

    $loggedFields = $items->flatMap(fn (array $item): array => collect($item['changes'])->pluck('field')->all());

    expect($loggedFields)->not->toContain('tax_code', 'birth_date', 'value', 'line1', 'latitude', 'longitude')
        ->and($loggedFields)->toContain('gender', 'label', 'postal_code');
});

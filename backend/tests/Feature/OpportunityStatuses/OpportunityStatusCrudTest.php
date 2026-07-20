<?php

use App\Models\Opportunity;
use App\Models\OpportunityStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('opportunityStatusUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function opportunityStatusUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("opportunity-statuses.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("opportunity-statuses.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// create — POST /api/opportunity-statuses (AC-003)
// ---------------------------------------------------------------------------

it('create: 201 + persists, submitted sort_order ignored (AC-003)', function () {
    $actor = opportunityStatusUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/opportunity-statuses', ['name' => 'Trattativa', 'color' => 'blue', 'group' => 'open', 'sort_order' => 2])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Trattativa')
        ->assertJsonPath('data.color', 'blue')
        ->assertJsonPath('data.group', 'open');

    $this->assertDatabaseHas('opportunity_statuses', ['name' => 'Trattativa', 'color' => 'blue', 'group' => 'open']);
});

it('create: 422 when name is missing', function () {
    $actor = opportunityStatusUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/opportunity-statuses', ['group' => 'open'])
        ->assertStatus(422)->assertJsonValidationErrors('name');
});

it('create: 422 when group is missing', function () {
    $actor = opportunityStatusUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/opportunity-statuses', ['name' => 'Trattativa'])
        ->assertStatus(422)->assertJsonValidationErrors('group');
});

it('create: 422 when group is not one of open/pending/closed', function () {
    $actor = opportunityStatusUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/opportunity-statuses', ['name' => 'Trattativa', 'group' => 'bogus'])
        ->assertStatus(422)->assertJsonValidationErrors('group');
});

// ---------------------------------------------------------------------------
// create — BR-3 unique name
// ---------------------------------------------------------------------------

it('create: 422 when name duplicates an existing status, no row created (BR-3)', function () {
    $actor = opportunityStatusUserWith(['create']);
    OpportunityStatus::factory()->create(['name' => 'Trattativa']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/opportunity-statuses', ['name' => 'Trattativa', 'group' => 'open'])
        ->assertStatus(422)->assertJsonValidationErrors('name');

    expect(OpportunityStatus::where('name', 'Trattativa')->count())->toBe(1);
});

// ---------------------------------------------------------------------------
// show — GET /api/opportunity-statuses/{opportunityStatus}
// ---------------------------------------------------------------------------

it('show: 200 with the full data shape', function () {
    $actor = opportunityStatusUserWith(['view']);
    $target = OpportunityStatus::factory()->create(['name' => 'Attiva', 'color' => 'blue', 'sort_order' => 3]);
    Sanctum::actingAs($actor);

    $this->getJson("/api/opportunity-statuses/{$target->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $target->id)
        ->assertJsonPath('data.name', 'Attiva')
        ->assertJsonPath('data.color', 'blue')
        ->assertJsonPath('data.sort_order', 3);
});

it('show: 404 for a non-existent opportunity status', function () {
    $actor = opportunityStatusUserWith(['view']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/opportunity-statuses/999999')->assertNotFound();
});

// ---------------------------------------------------------------------------
// update — PATCH /api/opportunity-statuses/{opportunityStatus}
// ---------------------------------------------------------------------------

it('update: PATCH partial {name} updates the opportunity status', function () {
    $actor = opportunityStatusUserWith(['update']);
    $target = OpportunityStatus::factory()->create(['name' => 'Before']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/opportunity-statuses/{$target->id}", ['name' => 'After'])
        ->assertOk()
        ->assertJsonPath('data.name', 'After');

    $this->assertDatabaseHas('opportunity_statuses', ['id' => $target->id, 'name' => 'After']);
});

it('update: 200 when re-submitting its OWN unchanged name (unique ignores self), submitted sort_order ignored', function () {
    $actor = opportunityStatusUserWith(['update']);
    $target = OpportunityStatus::factory()->create(['name' => 'Same', 'sort_order' => 1]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/opportunity-statuses/{$target->id}", ['name' => 'Same', 'sort_order' => 5])
        ->assertOk()
        ->assertJsonPath('data.name', 'Same')
        ->assertJsonPath('data.sort_order', 1);
});

it('update: 422 when name duplicates ANOTHER existing status', function () {
    $actor = opportunityStatusUserWith(['update']);
    OpportunityStatus::factory()->create(['name' => 'Taken']);
    $target = OpportunityStatus::factory()->create(['name' => 'Mine']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/opportunity-statuses/{$target->id}", ['name' => 'Taken'])
        ->assertStatus(422)->assertJsonValidationErrors('name');
});

// ---------------------------------------------------------------------------
// AC-006 — 403 without the permission on EVERY verb, no write
// ---------------------------------------------------------------------------

it('GET show: 403 without opportunity-statuses.view (AC-006)', function () {
    $actor = opportunityStatusUserWith([]);
    $target = OpportunityStatus::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/opportunity-statuses/{$target->id}")->assertForbidden();
});

it('POST create: 403 without opportunity-statuses.create, no row created (AC-006)', function () {
    $actor = opportunityStatusUserWith([]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/opportunity-statuses', ['name' => 'Nope', 'group' => 'open'])->assertForbidden();

    // spec 0043 (D-1/D-2): the create migration seeds the 3 mandatory system
    // rows ("Nuova"/"Chiusa con successo"/"Persa") unconditionally, so the
    // post-403 baseline is 3, not 0.
    expect(OpportunityStatus::count())->toBe(3);
});

it('PATCH update: 403 without opportunity-statuses.update, no change persisted (AC-006)', function () {
    $actor = opportunityStatusUserWith([]);
    $target = OpportunityStatus::factory()->create(['name' => 'Untouched']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/opportunity-statuses/{$target->id}", ['name' => 'Nope'])->assertForbidden();

    $this->assertDatabaseHas('opportunity_statuses', ['id' => $target->id, 'name' => 'Untouched']);
});

it('DELETE destroy: 403 without opportunity-statuses.delete, record still exists (AC-006)', function () {
    $actor = opportunityStatusUserWith([]);
    $target = OpportunityStatus::factory()->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/opportunity-statuses/{$target->id}")->assertForbidden();

    $this->assertDatabaseHas('opportunity_statuses', ['id' => $target->id]);
});

// ---------------------------------------------------------------------------
// delete — DELETE /api/opportunity-statuses/{opportunityStatus} (BR-2, AC-005)
// ---------------------------------------------------------------------------

it('delete: 409 when referenced by an Opportunity, status AND opportunity still exist (AC-005)', function () {
    $actor = opportunityStatusUserWith(['delete']);
    $target = OpportunityStatus::factory()->create();
    $opportunity = Opportunity::factory()->create(['opportunity_status_id' => $target->id]);
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/opportunity-statuses/{$target->id}")
        ->assertStatus(409)
        ->assertJsonPath('message', 'This opportunity status is used by an opportunity and cannot be deleted.');

    $this->assertDatabaseHas('opportunity_statuses', ['id' => $target->id]);
    $this->assertDatabaseHas('opportunities', ['id' => $opportunity->id]);
});

it('delete: 204 + removed when not referenced by anything (AC-005)', function () {
    $actor = opportunityStatusUserWith(['delete']);
    $target = OpportunityStatus::factory()->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/opportunity-statuses/{$target->id}")->assertNoContent();

    $this->assertDatabaseMissing('opportunity_statuses', ['id' => $target->id]);
});

it('delete: 403 without opportunity-statuses.delete', function () {
    $actor = opportunityStatusUserWith([]);
    $target = OpportunityStatus::factory()->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/opportunity-statuses/{$target->id}")->assertForbidden();
});

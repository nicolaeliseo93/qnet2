<?php

use App\Models\Lead;
use App\Models\LeadStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('leadStatusUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function leadStatusUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("lead-statuses.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("lead-statuses.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// create — POST /api/lead-statuses (AC-001)
// ---------------------------------------------------------------------------

it('create: 201 + persists, submitted sort_order ignored (AC-001, spec 0039 D-5)', function () {
    $actor = leadStatusUserWith(['create']);
    Sanctum::actingAs($actor);

    // requirement changed (spec 0039, D-5): sort_order is server-managed —
    // a submitted value is silently ignored (placement is automatic, AC-006).
    $this->postJson('/api/lead-statuses', ['name' => 'Qualified', 'color' => 'green', 'group' => 'open', 'sort_order' => 2])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Qualified')
        ->assertJsonPath('data.color', 'green')
        ->assertJsonPath('data.group', 'open');

    $this->assertDatabaseHas('lead_statuses', ['name' => 'Qualified', 'color' => 'green', 'group' => 'open']);
});

it('create: 422 when name is missing', function () {
    $actor = leadStatusUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/lead-statuses', ['group' => 'open'])
        ->assertStatus(422)->assertJsonValidationErrors('name');
});

it('create: 422 when group is missing (spec 0039 pivot)', function () {
    $actor = leadStatusUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/lead-statuses', ['name' => 'Qualified'])
        ->assertStatus(422)->assertJsonValidationErrors('group');
});

it('create: 422 when group is not one of open/pending/closed (spec 0039 pivot)', function () {
    $actor = leadStatusUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/lead-statuses', ['name' => 'Qualified', 'group' => 'bogus'])
        ->assertStatus(422)->assertJsonValidationErrors('group');
});

// ---------------------------------------------------------------------------
// create — BR-2/D-4 unique name (AC-002)
// ---------------------------------------------------------------------------

it('create: 422 when name duplicates an existing status, no row created (AC-002)', function () {
    $actor = leadStatusUserWith(['create']);
    LeadStatus::factory()->create(['name' => 'Qualified']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/lead-statuses', ['name' => 'Qualified', 'group' => 'open'])
        ->assertStatus(422)->assertJsonValidationErrors('name');

    expect(LeadStatus::where('name', 'Qualified')->count())->toBe(1);
});

// ---------------------------------------------------------------------------
// show — GET /api/lead-statuses/{leadStatus}
// ---------------------------------------------------------------------------

it('show: 200 with the full data shape', function () {
    $actor = leadStatusUserWith(['view']);
    $target = LeadStatus::factory()->create(['name' => 'Attivo', 'color' => 'blue', 'sort_order' => 3]);
    Sanctum::actingAs($actor);

    $this->getJson("/api/lead-statuses/{$target->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $target->id)
        ->assertJsonPath('data.name', 'Attivo')
        ->assertJsonPath('data.color', 'blue')
        ->assertJsonPath('data.sort_order', 3);
});

it('show: 404 for a non-existent lead status', function () {
    $actor = leadStatusUserWith(['view']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/lead-statuses/999999')->assertNotFound();
});

// ---------------------------------------------------------------------------
// update — PATCH /api/lead-statuses/{leadStatus}
// ---------------------------------------------------------------------------

it('update: PATCH partial {name} updates the lead status', function () {
    $actor = leadStatusUserWith(['update']);
    $target = LeadStatus::factory()->create(['name' => 'Before']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/lead-statuses/{$target->id}", ['name' => 'After'])
        ->assertOk()
        ->assertJsonPath('data.name', 'After');

    $this->assertDatabaseHas('lead_statuses', ['id' => $target->id, 'name' => 'After']);
});

it('update: 200 when re-submitting its OWN unchanged name (unique ignores self, AC-003), submitted sort_order ignored (spec 0039 D-5)', function () {
    $actor = leadStatusUserWith(['update']);
    $target = LeadStatus::factory()->create(['name' => 'Same', 'sort_order' => 1]);
    Sanctum::actingAs($actor);

    // requirement changed (spec 0039, D-5): sort_order left store/update —
    // a submitted value is silently ignored, the persisted one is untouched.
    $this->patchJson("/api/lead-statuses/{$target->id}", ['name' => 'Same', 'sort_order' => 5])
        ->assertOk()
        ->assertJsonPath('data.name', 'Same')
        ->assertJsonPath('data.sort_order', 1);
});

it('update: 422 when name duplicates ANOTHER existing status', function () {
    $actor = leadStatusUserWith(['update']);
    LeadStatus::factory()->create(['name' => 'Taken']);
    $target = LeadStatus::factory()->create(['name' => 'Mine']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/lead-statuses/{$target->id}", ['name' => 'Taken'])
        ->assertStatus(422)->assertJsonValidationErrors('name');
});

// ---------------------------------------------------------------------------
// AC-006 — 403 without the permission on EVERY verb, no write
// ---------------------------------------------------------------------------

it('GET show: 403 without lead-statuses.view (AC-006)', function () {
    $actor = leadStatusUserWith([]);
    $target = LeadStatus::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/lead-statuses/{$target->id}")->assertForbidden();
});

it('POST create: 403 without lead-statuses.create, no row created (AC-006)', function () {
    $actor = leadStatusUserWith([]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/lead-statuses', ['name' => 'Nope', 'group' => 'open'])->assertForbidden();

    // spec 0039 pivot (D-2): the migration seeds the 3 mandatory system rows
    // ("Nuovo"/"Chiuso con successo"/"Scartato") unconditionally, so the
    // post-403 baseline is 3, not 0 — requirement change, not a regression.
    expect(LeadStatus::count())->toBe(3);
});

it('PATCH update: 403 without lead-statuses.update, no change persisted (AC-006)', function () {
    $actor = leadStatusUserWith([]);
    $target = LeadStatus::factory()->create(['name' => 'Untouched']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/lead-statuses/{$target->id}", ['name' => 'Nope'])->assertForbidden();

    $this->assertDatabaseHas('lead_statuses', ['id' => $target->id, 'name' => 'Untouched']);
});

it('DELETE destroy: 403 without lead-statuses.delete, record still exists (AC-006)', function () {
    $actor = leadStatusUserWith([]);
    $target = LeadStatus::factory()->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/lead-statuses/{$target->id}")->assertForbidden();

    $this->assertDatabaseHas('lead_statuses', ['id' => $target->id]);
});

// ---------------------------------------------------------------------------
// delete — DELETE /api/lead-statuses/{leadStatus} (BR-3, AC-004/005)
// ---------------------------------------------------------------------------

it('delete: 409 when referenced by a Lead, status AND lead still exist (AC-005)', function () {
    $actor = leadStatusUserWith(['delete']);
    $target = LeadStatus::factory()->create();
    $lead = Lead::factory()->create(['lead_status_id' => $target->id]);
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/lead-statuses/{$target->id}")
        ->assertStatus(409)
        ->assertJsonPath('message', 'This lead status is used by a lead and cannot be deleted.');

    $this->assertDatabaseHas('lead_statuses', ['id' => $target->id]);
    $this->assertDatabaseHas('leads', ['id' => $lead->id]);
});

it('delete: 204 + removed when not referenced by anything (AC-004)', function () {
    $actor = leadStatusUserWith(['delete']);
    $target = LeadStatus::factory()->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/lead-statuses/{$target->id}")->assertNoContent();

    $this->assertDatabaseMissing('lead_statuses', ['id' => $target->id]);
});

it('delete: 403 without lead-statuses.delete', function () {
    $actor = leadStatusUserWith([]);
    $target = LeadStatus::factory()->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/lead-statuses/{$target->id}")->assertForbidden();
});

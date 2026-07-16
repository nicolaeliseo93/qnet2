<?php

use App\Enums\StatusGroup;
use App\Models\LeadStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| System-status rules for `lead-statuses` (spec 0039 pivot, D-2/D-5)
|--------------------------------------------------------------------------
|
| The 3 mandatory rows ("Nuovo"/"Chiuso con successo"/"Scartato") are seeded
| unconditionally by the system-status migration, so every test here reads
| them back rather than creating them (system_key is UNIQUE — a second
| 'new'/'won'/'discarded' row would violate it).
*/

if (! function_exists('leadStatusSystemUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function leadStatusSystemUserWith(array $abilities): User
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
// AC-003 — delete guard on system rows (single), custom rows unaffected
// ---------------------------------------------------------------------------

it('delete: 422 on the system "new" row, message names it, row persists (AC-003)', function () {
    $actor = leadStatusSystemUserWith(['delete']);
    $newStatus = LeadStatus::where('system_key', 'new')->firstOrFail();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/lead-statuses/{$newStatus->id}")
        ->assertStatus(422)
        ->assertJsonPath('message', "The '{$newStatus->name}' status is a system status and cannot be deleted.");

    $this->assertDatabaseHas('lead_statuses', ['id' => $newStatus->id]);
});

it('delete: 422 on the system "won" row (AC-003)', function () {
    $actor = leadStatusSystemUserWith(['delete']);
    $wonStatus = LeadStatus::where('system_key', 'won')->firstOrFail();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/lead-statuses/{$wonStatus->id}")->assertStatus(422);

    $this->assertDatabaseHas('lead_statuses', ['id' => $wonStatus->id]);
});

it('delete: 422 on the system "discarded" row (AC-003)', function () {
    $actor = leadStatusSystemUserWith(['delete']);
    $discardedStatus = LeadStatus::where('system_key', 'discarded')->firstOrFail();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/lead-statuses/{$discardedStatus->id}")->assertStatus(422);

    $this->assertDatabaseHas('lead_statuses', ['id' => $discardedStatus->id]);
});

it('delete: a custom, unreferenced row still returns 204 (AC-003, invariant)', function () {
    $actor = leadStatusSystemUserWith(['delete']);
    $custom = LeadStatus::factory()->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/lead-statuses/{$custom->id}")->assertNoContent();
});

// ---------------------------------------------------------------------------
// AC-003 — delete guard via the generic bulk-delete endpoint (table framework)
// ---------------------------------------------------------------------------

it('bulk-delete: a system row is rejected, a custom unreferenced row succeeds (AC-003)', function () {
    $actor = leadStatusSystemUserWith(['delete', 'viewAny']);
    $newStatus = LeadStatus::where('system_key', 'new')->firstOrFail();
    $custom = LeadStatus::factory()->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/lead-statuses/bulk-delete', ['ids' => [$newStatus->id, $custom->id]])
        ->assertOk();

    expect($response->json('data.deleted'))->toBe(1)
        ->and(collect($response->json('data.failed'))->pluck('id'))->toContain($newStatus->id)
        ->and(collect($response->json('data.failed'))->firstWhere('id', $newStatus->id)['reason'])->toBe('guarded');

    $this->assertDatabaseHas('lead_statuses', ['id' => $newStatus->id]);
    $this->assertDatabaseMissing('lead_statuses', ['id' => $custom->id]);
});

// ---------------------------------------------------------------------------
// AC-004 — update guard: name/color allowed, group rejected
// ---------------------------------------------------------------------------

it('update: 200 when a system row changes ONLY name/color (AC-004)', function () {
    $actor = leadStatusSystemUserWith(['update']);
    $newStatus = LeadStatus::where('system_key', 'new')->firstOrFail();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/lead-statuses/{$newStatus->id}", ['name' => 'Nuovissimo', 'color' => 'teal'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Nuovissimo')
        ->assertJsonPath('data.color', 'teal')
        ->assertJsonPath('data.system_key', 'new');

    $this->assertDatabaseHas('lead_statuses', ['id' => $newStatus->id, 'name' => 'Nuovissimo', 'color' => 'teal']);
});

it('update: 422 when a system row payload includes group, nothing persists (AC-004)', function () {
    $actor = leadStatusSystemUserWith(['update']);
    $newStatus = LeadStatus::where('system_key', 'new')->firstOrFail();
    // "Nuovo" already carries its D-2 FIXED group ("open", assigned at
    // migration time) — the assertion below checks it stays untouched.
    $originalGroup = $newStatus->group->value;
    Sanctum::actingAs($actor);

    $this->patchJson("/api/lead-statuses/{$newStatus->id}", ['group' => 'closed'])
        ->assertStatus(422)
        ->assertJsonPath('message', 'System statuses accept only name and color changes.');

    $this->assertDatabaseHas('lead_statuses', ['id' => $newStatus->id, 'group' => $originalGroup]);
});

it('update: a custom row accepts group (AC-008)', function () {
    $actor = leadStatusSystemUserWith(['update']);
    $custom = LeadStatus::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/lead-statuses/{$custom->id}", ['group' => 'pending'])
        ->assertOk()
        ->assertJsonPath('data.group', 'pending');
});

// ---------------------------------------------------------------------------
// AC-008 — group on create + invalid value + mapRow exposure
// ---------------------------------------------------------------------------

it('create: a custom row with a valid group returns it (AC-008)', function () {
    $actor = leadStatusSystemUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/lead-statuses', ['name' => 'Attesa', 'group' => 'pending'])
        ->assertCreated()
        ->assertJsonPath('data.group', 'pending');
});

it('create: 422 when group is not one of open/pending/closed (AC-008)', function () {
    $actor = leadStatusSystemUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/lead-statuses', ['name' => 'Attesa', 'group' => 'bogus'])
        ->assertStatus(422)->assertJsonValidationErrors('group');
});

it('table rows: mapRow exposes system_key and group (AC-008)', function () {
    $actor = leadStatusSystemUserWith(['viewAny']);
    LeadStatus::factory()->group(StatusGroup::Pending)->create(['name' => 'Con Gruppo']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/lead-statuses/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();

    $newRow = collect($response->json('items'))->firstWhere('system_key', 'new');
    expect($newRow)->not->toBeNull()
        ->and($newRow['actions'])->not->toContain('delete');

    $groupedRow = collect($response->json('items'))->firstWhere('name', 'Con Gruppo');
    expect($groupedRow['group'])->toBe('pending');
});

// ---------------------------------------------------------------------------
// AC-006 — automatic placement of a new custom row (before "Chiuso con
// successo"/"Scartato")
// ---------------------------------------------------------------------------

it('create: a new custom row is placed last among customs, before the closed tail (AC-006)', function () {
    $actor = leadStatusSystemUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/lead-statuses', ['name' => 'Primo Custom', 'group' => 'open'])->assertCreated();

    $ordered = LeadStatus::query()->orderBy('sort_order')->pluck('system_key', 'name');

    expect($ordered->keys()->first())->toBe('Nuovo')
        ->and($ordered->keys()->last())->toBe('Scartato')
        ->and($ordered->keys()->get($ordered->keys()->count() - 2))->toBe('Chiuso con successo');
});

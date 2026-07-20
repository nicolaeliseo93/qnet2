<?php

use App\Enums\StatusGroup;
use App\Models\OpportunityStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/*
|--------------------------------------------------------------------------
| System-status rules for `opportunity-statuses` (spec 0043, D-1/D-2)
|--------------------------------------------------------------------------
|
| The 3 mandatory rows ("Nuova"/"Chiusa con successo"/"Persa") are seeded
| unconditionally by the create-table migration, so every test here reads
| them back rather than creating them (system_key is UNIQUE — a second
| 'new'/'won'/'lost' row would violate it).
*/

if (! function_exists('opportunityStatusSystemUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function opportunityStatusSystemUserWith(array $abilities): User
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
// BR-4/BR-5 — delete guard on system rows (single), custom rows unaffected
// ---------------------------------------------------------------------------

it('delete: 422 on the system "new" row, message names it, row persists (BR-5)', function () {
    $actor = opportunityStatusSystemUserWith(['delete']);
    $newStatus = OpportunityStatus::where('system_key', 'new')->firstOrFail();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/opportunity-statuses/{$newStatus->id}")
        ->assertStatus(422)
        ->assertJsonPath('message', "The '{$newStatus->name}' status is a system status and cannot be deleted.");

    $this->assertDatabaseHas('opportunity_statuses', ['id' => $newStatus->id]);
});

it('delete: 422 on the system "won" row (BR-5)', function () {
    $actor = opportunityStatusSystemUserWith(['delete']);
    $wonStatus = OpportunityStatus::where('system_key', 'won')->firstOrFail();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/opportunity-statuses/{$wonStatus->id}")->assertStatus(422);

    $this->assertDatabaseHas('opportunity_statuses', ['id' => $wonStatus->id]);
});

it('delete: 422 on the system "lost" row (BR-5)', function () {
    $actor = opportunityStatusSystemUserWith(['delete']);
    $lostStatus = OpportunityStatus::where('system_key', 'lost')->firstOrFail();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/opportunity-statuses/{$lostStatus->id}")->assertStatus(422);

    $this->assertDatabaseHas('opportunity_statuses', ['id' => $lostStatus->id]);
});

it('delete: a custom, unreferenced row still returns 204 (invariant)', function () {
    $actor = opportunityStatusSystemUserWith(['delete']);
    $custom = OpportunityStatus::factory()->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/opportunity-statuses/{$custom->id}")->assertNoContent();
});

// ---------------------------------------------------------------------------
// BR-4 — delete guard via the generic bulk-delete endpoint (table framework)
// ---------------------------------------------------------------------------

it('bulk-delete: a system row is rejected, a custom unreferenced row succeeds (BR-4)', function () {
    $actor = opportunityStatusSystemUserWith(['delete', 'viewAny']);
    $newStatus = OpportunityStatus::where('system_key', 'new')->firstOrFail();
    $custom = OpportunityStatus::factory()->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/opportunity-statuses/bulk-delete', ['ids' => [$newStatus->id, $custom->id]])
        ->assertOk();

    expect($response->json('data.deleted'))->toBe(1)
        ->and(collect($response->json('data.failed'))->pluck('id'))->toContain($newStatus->id)
        ->and(collect($response->json('data.failed'))->firstWhere('id', $newStatus->id)['reason'])->toBe('guarded');

    $this->assertDatabaseHas('opportunity_statuses', ['id' => $newStatus->id]);
    $this->assertDatabaseMissing('opportunity_statuses', ['id' => $custom->id]);
});

// ---------------------------------------------------------------------------
// BR-5 — update guard: name/color allowed, group rejected
// ---------------------------------------------------------------------------

it('update: 200 when a system row changes ONLY name/color (BR-5)', function () {
    $actor = opportunityStatusSystemUserWith(['update']);
    $newStatus = OpportunityStatus::where('system_key', 'new')->firstOrFail();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/opportunity-statuses/{$newStatus->id}", ['name' => 'Nuovissima', 'color' => 'teal'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Nuovissima')
        ->assertJsonPath('data.color', 'teal')
        ->assertJsonPath('data.system_key', 'new');

    $this->assertDatabaseHas('opportunity_statuses', ['id' => $newStatus->id, 'name' => 'Nuovissima', 'color' => 'teal']);
});

it('update: 422 when a system row payload includes group, nothing persists (BR-5)', function () {
    $actor = opportunityStatusSystemUserWith(['update']);
    $newStatus = OpportunityStatus::where('system_key', 'new')->firstOrFail();
    $originalGroup = $newStatus->group->value;
    Sanctum::actingAs($actor);

    $this->patchJson("/api/opportunity-statuses/{$newStatus->id}", ['group' => 'closed'])
        ->assertStatus(422)
        ->assertJsonPath('message', 'System statuses accept only name and color changes.');

    $this->assertDatabaseHas('opportunity_statuses', ['id' => $newStatus->id, 'group' => $originalGroup]);
});

it('update: a custom row accepts group', function () {
    $actor = opportunityStatusSystemUserWith(['update']);
    $custom = OpportunityStatus::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/opportunity-statuses/{$custom->id}", ['group' => 'pending'])
        ->assertOk()
        ->assertJsonPath('data.group', 'pending');
});

// ---------------------------------------------------------------------------
// group on create + invalid value + mapRow exposure
// ---------------------------------------------------------------------------

it('create: a custom row with a valid group returns it', function () {
    $actor = opportunityStatusSystemUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/opportunity-statuses', ['name' => 'Attesa', 'group' => 'pending'])
        ->assertCreated()
        ->assertJsonPath('data.group', 'pending');
});

it('create: 422 when group is not one of open/pending/closed', function () {
    $actor = opportunityStatusSystemUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/opportunity-statuses', ['name' => 'Attesa', 'group' => 'bogus'])
        ->assertStatus(422)->assertJsonValidationErrors('group');
});

it('table rows: mapRow exposes system_key and group', function () {
    $actor = opportunityStatusSystemUserWith(['viewAny']);
    OpportunityStatus::factory()->group(StatusGroup::Pending)->create(['name' => 'Con Gruppo']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/opportunity-statuses/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();

    $newRow = collect($response->json('items'))->firstWhere('system_key', 'new');
    expect($newRow)->not->toBeNull()
        ->and($newRow['actions'])->not->toContain('delete');

    $groupedRow = collect($response->json('items'))->firstWhere('name', 'Con Gruppo');
    expect($groupedRow['group'])->toBe('pending');
});

// ---------------------------------------------------------------------------
// BR-6 — automatic placement of a new custom row (before "Chiusa con
// successo"/"Persa")
// ---------------------------------------------------------------------------

it('create: a new custom row is placed last among customs, before the closed tail (BR-6)', function () {
    $actor = opportunityStatusSystemUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/opportunity-statuses', ['name' => 'Primo Custom', 'group' => 'open'])->assertCreated();

    $ordered = OpportunityStatus::query()->orderBy('sort_order')->pluck('system_key', 'name');

    expect($ordered->keys()->first())->toBe('Nuova')
        ->and($ordered->keys()->last())->toBe('Persa')
        ->and($ordered->keys()->get($ordered->keys()->count() - 2))->toBe('Chiusa con successo');
});

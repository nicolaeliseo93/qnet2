<?php

use App\Models\OpportunityWorkflow;
use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('opportunityWorkflowUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function opportunityWorkflowUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity'] as $ability) {
            Permission::findOrCreate("opportunity-workflows.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("opportunity-workflows.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// AC-020 — every endpoint applies server-side authz (403 without the
// matching permission, no write persisted)
// ---------------------------------------------------------------------------

it('GET show: 403 without opportunity-workflows.view (AC-020)', function () {
    $actor = opportunityWorkflowUserWith([]);
    $target = OpportunityWorkflow::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/opportunity-workflows/{$target->id}")->assertForbidden();
});

it('POST create: 403 without opportunity-workflows.create, no row created (AC-020)', function () {
    $actor = opportunityWorkflowUserWith([]);
    $source = Source::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/opportunity-workflows', [
        'name' => 'Nope',
        'criteria' => [['field' => 'source_id', 'value_id' => $source->id]],
    ])->assertForbidden();

    expect(OpportunityWorkflow::count())->toBe(0);
});

it('PATCH update: 403 without opportunity-workflows.update, no change persisted (AC-020)', function () {
    $actor = opportunityWorkflowUserWith([]);
    $target = OpportunityWorkflow::factory()->create(['name' => 'Untouched']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/opportunity-workflows/{$target->id}", ['name' => 'Nope'])->assertForbidden();

    $this->assertDatabaseHas('opportunity_workflows', ['id' => $target->id, 'name' => 'Untouched']);
});

it('DELETE destroy: 403 without opportunity-workflows.delete, record still exists (AC-020)', function () {
    $actor = opportunityWorkflowUserWith([]);
    $target = OpportunityWorkflow::factory()->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/opportunity-workflows/{$target->id}")->assertForbidden();

    $this->assertDatabaseHas('opportunity_workflows', ['id' => $target->id]);
});

it('GET criterion-fields: 403 without opportunity-workflows.view (AC-020)', function () {
    $actor = opportunityWorkflowUserWith([]);
    Sanctum::actingAs($actor);

    $this->getJson('/api/opportunity-workflows/criterion-fields')->assertForbidden();
});

it('GET default-statuses: 403 without opportunity-workflows.view (AC-020)', function () {
    $actor = opportunityWorkflowUserWith([]);
    Sanctum::actingAs($actor);

    $this->getJson('/api/opportunity-workflows/default-statuses')->assertForbidden();
});

it('PUT default-statuses: 403 without opportunity-workflows.update (AC-020)', function () {
    $actor = opportunityWorkflowUserWith(['view']);
    Sanctum::actingAs($actor);

    $this->putJson('/api/opportunity-workflows/default-statuses', [
        'statuses' => [['name' => 'X', 'group' => 'open']],
    ])->assertForbidden();
});

// ---------------------------------------------------------------------------
// AC-021 — create/update/delete are registered in the Activity Log
// ---------------------------------------------------------------------------

it('create is recorded in the activity log (AC-021)', function () {
    $actor = opportunityWorkflowUserWith(['create']);
    $source = Source::factory()->create();
    Sanctum::actingAs($actor);

    $created = $this->postJson('/api/opportunity-workflows', [
        'name' => 'Logged Create',
        'criteria' => [['field' => 'source_id', 'value_id' => $source->id]],
    ])->assertCreated()->json('data');

    $this->assertDatabaseHas('activity_log', [
        'log_name' => 'opportunity_workflows',
        'subject_type' => 'opportunity_workflow',
        'subject_id' => $created['id'],
        'event' => 'created',
        'causer_id' => $actor->id,
    ]);
});

it('update is recorded in the activity log (AC-021)', function () {
    $actor = opportunityWorkflowUserWith(['create', 'update']);
    $source = Source::factory()->create();
    Sanctum::actingAs($actor);

    $created = $this->postJson('/api/opportunity-workflows', [
        'name' => 'Logged Update',
        'criteria' => [['field' => 'source_id', 'value_id' => $source->id]],
    ])->assertCreated()->json('data');

    $this->patchJson("/api/opportunity-workflows/{$created['id']}", ['name' => 'Logged Update Renamed'])->assertOk();

    $this->assertDatabaseHas('activity_log', [
        'log_name' => 'opportunity_workflows',
        'subject_type' => 'opportunity_workflow',
        'subject_id' => $created['id'],
        'event' => 'updated',
        'causer_id' => $actor->id,
    ]);
});

it('delete is recorded in the activity log (AC-021)', function () {
    $actor = opportunityWorkflowUserWith(['create', 'delete']);
    $source = Source::factory()->create();
    Sanctum::actingAs($actor);

    $created = $this->postJson('/api/opportunity-workflows', [
        'name' => 'Logged Delete',
        'criteria' => [['field' => 'source_id', 'value_id' => $source->id]],
    ])->assertCreated()->json('data');

    $this->deleteJson("/api/opportunity-workflows/{$created['id']}")->assertNoContent();

    $this->assertDatabaseHas('activity_log', [
        'log_name' => 'opportunity_workflows',
        'subject_type' => 'opportunity_workflow',
        'subject_id' => $created['id'],
        'event' => 'deleted',
        'causer_id' => $actor->id,
    ]);
});

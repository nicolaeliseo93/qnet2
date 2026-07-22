<?php

use App\Models\Note;
use App\Models\Opportunity;
use App\Models\OpportunityWorkflow;
use App\Models\OpportunityWorkflowStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Activitylog\Models\Activity;
use Spatie\Permission\Models\Permission;

// PATCH /api/tables/request-management/rows/{row} — inline edit of the
// working-status column (`workflow_status`, field `opportunity_workflow_status_id`,
// spec 0054 D-5): the mandatory-note rule, activated now that D-5 was
// resolved (RequestManagementService::updateWork() is the ONE choke point
// for both this channel and the work panel's UpdateRequestRequest).

uses(RefreshDatabase::class);

if (! function_exists('workflowInlineEditActor')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function workflowInlineEditActor(array $abilities, bool $canCreateNotes = true): User
    {
        foreach (['viewAny', 'view', 'update', 'viewAll'] as $ability) {
            Permission::findOrCreate("request-management.{$ability}");
        }
        Permission::findOrCreate('notes.create');

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("request-management.{$ability}");
        }

        if ($canCreateNotes) {
            $user->givePermissionTo('notes.create');
        }

        return $user;
    }
}

if (! function_exists('managedOpportunityForWorkflowInlineEdit')) {
    function managedOpportunityForWorkflowInlineEdit(User $manager): Opportunity
    {
        $opportunity = Opportunity::factory()->create();
        $opportunity->managers()->sync([$manager->id => ['position' => 2]]);

        return $opportunity;
    }
}

/**
 * A status belonging to the GLOBAL default set (opportunity_workflow_id
 * null) — resolvable for any opportunity that matches no active custom
 * workflow, mirroring OpportunityWorkflowResolver::statusesFor(null).
 */
if (! function_exists('globalWorkflowStatus')) {
    function globalWorkflowStatus(bool $requiresNote): OpportunityWorkflowStatus
    {
        return OpportunityWorkflowStatus::factory()->create([
            'opportunity_workflow_id' => null,
            'requires_note' => $requiresNote,
            'sort_order' => 99,
        ]);
    }
}

it('GET /api/tables/request-management/columns: workflow_status emits options with requires_note per entry', function () {
    $actor = workflowInlineEditActor(['viewAny', 'view', 'update']);
    $required = globalWorkflowStatus(true);
    Sanctum::actingAs($actor);

    $columns = collect($this->getJson('/api/tables/request-management/columns')->assertOk()->json('data.columns'))
        ->keyBy('id');

    $option = collect($columns['workflow_status']['options'])->firstWhere('value', $required->id);
    expect($option)->not->toBeNull()
        ->and($option['requires_note'])->toBeTrue();
});

// ---------------------------------------------------------------------------
// AC-008 — target status without requires_note
// ---------------------------------------------------------------------------

it('AC-008: PATCH to a status WITHOUT requires_note -> 200, no note created', function () {
    $actor = workflowInlineEditActor(['viewAny', 'view', 'update']);
    $opportunity = managedOpportunityForWorkflowInlineEdit($actor);
    $target = globalWorkflowStatus(false);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/request-management/rows/{$opportunity->id}", [
        'column' => 'workflow_status',
        'value' => $target->id,
    ])->assertOk();

    expect($opportunity->fresh()->opportunity_workflow_status_id)->toBe($target->id);
    expect(Note::query()->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// AC-009 — target status WITH requires_note, note missing/blank
// ---------------------------------------------------------------------------

it('AC-009: PATCH to a requires_note status with no note -> 422, status unchanged, no note created', function () {
    $actor = workflowInlineEditActor(['viewAny', 'view', 'update']);
    $opportunity = managedOpportunityForWorkflowInlineEdit($actor);
    $originalStatusId = $opportunity->opportunity_workflow_status_id;
    $target = globalWorkflowStatus(true);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/request-management/rows/{$opportunity->id}", [
        'column' => 'workflow_status',
        'value' => $target->id,
    ])->assertStatus(422);

    expect($opportunity->fresh()->opportunity_workflow_status_id)->toBe($originalStatusId);
    expect(Note::query()->count())->toBe(0);
});

it('AC-009: PATCH to a requires_note status with a blank note -> 422, status unchanged', function () {
    $actor = workflowInlineEditActor(['viewAny', 'view', 'update']);
    $opportunity = managedOpportunityForWorkflowInlineEdit($actor);
    $originalStatusId = $opportunity->opportunity_workflow_status_id;
    $target = globalWorkflowStatus(true);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/request-management/rows/{$opportunity->id}", [
        'column' => 'workflow_status',
        'value' => $target->id,
        'note' => '   ',
    ])->assertStatus(422);

    expect($opportunity->fresh()->opportunity_workflow_status_id)->toBe($originalStatusId);
});

// ---------------------------------------------------------------------------
// AC-010 — target status WITH requires_note, note provided: atomic write
// ---------------------------------------------------------------------------

it('AC-010: PATCH to a requires_note status WITH a note -> 200, status changed AND note created (same mechanism/author)', function () {
    $actor = workflowInlineEditActor(['viewAny', 'view', 'update']);
    $opportunity = managedOpportunityForWorkflowInlineEdit($actor);
    $target = globalWorkflowStatus(true);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/request-management/rows/{$opportunity->id}", [
        'column' => 'workflow_status',
        'value' => $target->id,
        'note' => 'Client confirmed the new commercial terms.',
    ])->assertOk();

    expect($opportunity->fresh()->opportunity_workflow_status_id)->toBe($target->id);

    $note = Note::query()->where('notable_type', $opportunity->getMorphClass())->where('notable_id', $opportunity->id)->sole();
    expect($note->body)->toBe('Client confirmed the new commercial terms.')
        ->and($note->user_id)->toBe($actor->id);
});

it('AC-010: when the note fails (actor lacks notes.create), the status change also rolls back (atomicity)', function () {
    $actor = workflowInlineEditActor(['viewAny', 'view', 'update'], canCreateNotes: false);
    $opportunity = managedOpportunityForWorkflowInlineEdit($actor);
    $originalStatusId = $opportunity->opportunity_workflow_status_id;
    $target = globalWorkflowStatus(true);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/request-management/rows/{$opportunity->id}", [
        'column' => 'workflow_status',
        'value' => $target->id,
        'note' => 'Attempted note without permission.',
    ])->assertForbidden();

    expect($opportunity->fresh()->opportunity_workflow_status_id)->toBe($originalStatusId);
    expect(Note::query()->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// AC-011 — status outside the resolved workflow set
// ---------------------------------------------------------------------------

it('AC-011: a status id that does not belong to the opportunity\'s resolved workflow -> 422', function () {
    $actor = workflowInlineEditActor(['viewAny', 'view', 'update']);
    $opportunity = managedOpportunityForWorkflowInlineEdit($actor);
    $originalStatusId = $opportunity->opportunity_workflow_status_id;

    // A status belonging to an UNRELATED, non-null workflow — never resolved
    // for this opportunity (which matches no active custom workflow).
    $foreignStatus = OpportunityWorkflowStatus::factory()->create([
        'opportunity_workflow_id' => OpportunityWorkflow::factory()->create(['is_active' => false]),
    ]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/request-management/rows/{$opportunity->id}", [
        'column' => 'workflow_status',
        'value' => $foreignStatus->id,
    ])->assertStatus(422);

    expect($opportunity->fresh()->opportunity_workflow_status_id)->toBe($originalStatusId);
});

// ---------------------------------------------------------------------------
// AC-012 — `note` on a column that does not accept it
// ---------------------------------------------------------------------------

it('AC-012: `note` sent on `next_callback_at` (which does not accept one) -> 422', function () {
    $actor = workflowInlineEditActor(['viewAny', 'view', 'update']);
    $opportunity = managedOpportunityForWorkflowInlineEdit($actor);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/request-management/rows/{$opportunity->id}", [
        'column' => 'next_callback_at',
        'value' => now()->addDay()->format('Y-m-d\TH:i'),
        'note' => 'Should not be accepted here.',
    ])->assertStatus(422);
});

// ---------------------------------------------------------------------------
// AC-014 — audit
// ---------------------------------------------------------------------------

it('AC-014: a successful status change writes an activity-log entry', function () {
    $actor = workflowInlineEditActor(['viewAny', 'view', 'update']);
    $opportunity = managedOpportunityForWorkflowInlineEdit($actor);
    $target = globalWorkflowStatus(false);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/request-management/rows/{$opportunity->id}", [
        'column' => 'workflow_status',
        'value' => $target->id,
    ])->assertOk();

    $activity = Activity::query()
        ->where('subject_type', $opportunity->getMorphClass())
        ->where('subject_id', $opportunity->id)
        ->where('event', 'updated')
        ->latest('id')
        ->first();

    expect($activity)->not->toBeNull();
    expect($activity->properties->get('attributes'))->toHaveKey('opportunity_workflow_status_id', $target->id);
});

// ---------------------------------------------------------------------------
// AC-015 — scope unaffected by the new column
// ---------------------------------------------------------------------------

it('AC-015: an operator not managing the record and without viewAll receives 404 on workflow_status PATCH', function () {
    $actor = workflowInlineEditActor(['viewAny', 'view', 'update']);
    $opportunity = Opportunity::factory()->create();
    $otherManager = User::factory()->create();
    $opportunity->managers()->sync([$otherManager->id => ['position' => 2]]);
    $target = globalWorkflowStatus(false);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/request-management/rows/{$opportunity->id}", [
        'column' => 'workflow_status',
        'value' => $target->id,
    ])->assertNotFound();
});

it('resending the SAME status requires no note, even when it requires_note', function () {
    $actor = workflowInlineEditActor(['viewAny', 'view', 'update']);
    $opportunity = managedOpportunityForWorkflowInlineEdit($actor);
    $current = globalWorkflowStatus(true);
    $opportunity->forceFill(['opportunity_workflow_status_id' => $current->id])->save();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/tables/request-management/rows/{$opportunity->id}", [
        'column' => 'workflow_status',
        'value' => $current->id,
    ])->assertOk();

    expect(Note::query()->count())->toBe(0);
});

<?php

use App\Models\Note;
use App\Models\Opportunity;
use App\Models\OpportunityWorkflowStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// PATCH /api/request-management/{opportunity} — the WORK PANEL channel of the
// mandatory-note rule (spec 0054 D-5). The rule itself lives in
// RequestManagementService::updateWork(), but the note only reaches it if
// UpdateRequestRequest declares the key and the controller forwards it:
// without both, safe()/only() strips `note` and every advance to a
// requires_note status is rejected as note-less even when the user filled it in.

uses(RefreshDatabase::class);

if (! function_exists('workPanelNoteActor')) {
    function workPanelNoteActor(): User
    {
        foreach (['viewAny', 'view', 'update'] as $ability) {
            Permission::findOrCreate("request-management.{$ability}");
        }
        Permission::findOrCreate('notes.create');

        $user = User::factory()->create();
        $user->givePermissionTo([
            'request-management.viewAny',
            'request-management.view',
            'request-management.update',
            'notes.create',
        ]);

        return $user;
    }
}

if (! function_exists('workPanelNoteOpportunity')) {
    function workPanelNoteOpportunity(User $manager): Opportunity
    {
        $opportunity = Opportunity::factory()->create();
        $opportunity->managers()->sync([$manager->id => ['position' => 2]]);

        return $opportunity;
    }
}

if (! function_exists('workPanelNoteStatus')) {
    function workPanelNoteStatus(bool $requiresNote): OpportunityWorkflowStatus
    {
        return OpportunityWorkflowStatus::factory()->create([
            'opportunity_workflow_id' => null,
            'requires_note' => $requiresNote,
            'sort_order' => 99,
        ]);
    }
}

it('D-5 (panel): advancing to a requires_note status WITH a note -> 200, status changed and note created', function () {
    $actor = workPanelNoteActor();
    $opportunity = workPanelNoteOpportunity($actor);
    $target = workPanelNoteStatus(true);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$opportunity->id}", [
        'opportunity_workflow_status_id' => $target->id,
        'note' => 'Client confirmed the new commercial terms.',
    ])->assertOk();

    expect($opportunity->fresh()->opportunity_workflow_status_id)->toBe($target->id);

    $note = Note::query()
        ->where('notable_type', $opportunity->getMorphClass())
        ->where('notable_id', $opportunity->id)
        ->sole();

    expect($note->body)->toBe('Client confirmed the new commercial terms.')
        ->and($note->user_id)->toBe($actor->id);
});

it('D-5 (panel): advancing to a requires_note status with NO note -> 422, status unchanged', function () {
    $actor = workPanelNoteActor();
    $opportunity = workPanelNoteOpportunity($actor);
    $originalStatusId = $opportunity->opportunity_workflow_status_id;
    $target = workPanelNoteStatus(true);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$opportunity->id}", [
        'opportunity_workflow_status_id' => $target->id,
    ])->assertStatus(422)->assertJsonValidationErrors('note');

    expect($opportunity->fresh()->opportunity_workflow_status_id)->toBe($originalStatusId);
    expect(Note::query()->count())->toBe(0);
});

it('D-5 (panel): advancing to a requires_note status with a blank note -> 422, status unchanged', function () {
    $actor = workPanelNoteActor();
    $opportunity = workPanelNoteOpportunity($actor);
    $originalStatusId = $opportunity->opportunity_workflow_status_id;
    $target = workPanelNoteStatus(true);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$opportunity->id}", [
        'opportunity_workflow_status_id' => $target->id,
        'note' => '   ',
    ])->assertStatus(422);

    expect($opportunity->fresh()->opportunity_workflow_status_id)->toBe($originalStatusId);
    expect(Note::query()->count())->toBe(0);
});

it('D-5 (panel): a status WITHOUT requires_note needs no note -> 200, no note created', function () {
    $actor = workPanelNoteActor();
    $opportunity = workPanelNoteOpportunity($actor);
    $target = workPanelNoteStatus(false);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/request-management/{$opportunity->id}", [
        'opportunity_workflow_status_id' => $target->id,
    ])->assertOk();

    expect($opportunity->fresh()->opportunity_workflow_status_id)->toBe($target->id);
    expect(Note::query()->count())->toBe(0);
});

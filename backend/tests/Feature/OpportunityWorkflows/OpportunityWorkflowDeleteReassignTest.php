<?php

use App\Models\Opportunity;
use App\Models\OpportunityWorkflow;
use App\Models\OpportunityWorkflowStatus;
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
// AC-018 — deleting a workflow re-resolves every opportunity that referenced
// one of its statuses; none is left orphaned.
// ---------------------------------------------------------------------------

it('delete: re-resolves every impacted opportunity to the global open row when nothing else matches (AC-018)', function () {
    $actor = opportunityWorkflowUserWith(['delete']);

    $source = Source::factory()->create();
    $workflow = OpportunityWorkflow::factory()->create(['is_active' => true]);
    $workflow->criteria()->create(['field' => 'source_id', 'value_id' => $source->id]);

    $openStatus = OpportunityWorkflowStatus::factory()
        ->system('open')
        ->create(['opportunity_workflow_id' => $workflow->id]);
    $closedStatus = OpportunityWorkflowStatus::factory()
        ->system('closed_won')
        ->create(['opportunity_workflow_id' => $workflow->id]);

    $openOpportunity = Opportunity::factory()->create([
        'source_id' => $source->id,
        'opportunity_workflow_status_id' => $openStatus->id,
    ]);
    $closedOpportunity = Opportunity::factory()->create([
        'source_id' => $source->id,
        'opportunity_workflow_status_id' => $closedStatus->id,
    ]);
    $globalOpenBefore = OpportunityWorkflowStatus::whereNull('opportunity_workflow_id')->where('system_key', 'open')->sole();
    // Never referencing the deleted workflow's own statuses (a plain
    // factory row, not run through OpportunityService, so this is set
    // explicitly rather than resolver-derived) — must stay untouched.
    $unrelatedOpportunity = Opportunity::factory()->create([
        'source_id' => null,
        'opportunity_workflow_status_id' => $globalOpenBefore->id,
    ]);
    $untouchedStatusId = $unrelatedOpportunity->opportunity_workflow_status_id;

    Sanctum::actingAs($actor);

    $this->deleteJson("/api/opportunity-workflows/{$workflow->id}")->assertNoContent();

    $globalOpen = OpportunityWorkflowStatus::whereNull('opportunity_workflow_id')->where('system_key', 'open')->sole();

    // Both impacted opportunities land on the GLOBAL set's 'open' row: the
    // `opportunity_workflow_status_id` FK is `nullOnDelete` (spec 0047), so
    // by the time OpportunityWorkflowResolver::resolveAndAssign() runs the
    // PREVIOUS status is already gone — there is no system_key left to
    // re-map by (D3's system_key mapping only applies when the CURRENT
    // status row still exists to read it from). The invariant AC-018
    // actually guarantees — no opportunity left orphaned/null — still holds.
    $this->assertDatabaseHas('opportunities', [
        'id' => $openOpportunity->id,
        'opportunity_workflow_status_id' => $globalOpen->id,
    ]);
    $this->assertDatabaseHas('opportunities', [
        'id' => $closedOpportunity->id,
        'opportunity_workflow_status_id' => $globalOpen->id,
    ]);

    // An opportunity never referencing the deleted workflow's statuses is
    // left untouched.
    $this->assertDatabaseHas('opportunities', [
        'id' => $unrelatedOpportunity->id,
        'opportunity_workflow_status_id' => $untouchedStatusId,
    ]);

    expect(Opportunity::whereNull('opportunity_workflow_status_id')->count())->toBe(0);
});

it('delete via table bulk-delete also re-resolves impacted opportunities (AC-018)', function () {
    $actor = opportunityWorkflowUserWith(['delete', 'viewAny']);

    $source = Source::factory()->create();
    $workflow = OpportunityWorkflow::factory()->create(['is_active' => true]);
    $workflow->criteria()->create(['field' => 'source_id', 'value_id' => $source->id]);

    $openStatus = OpportunityWorkflowStatus::factory()
        ->system('open')
        ->create(['opportunity_workflow_id' => $workflow->id]);
    OpportunityWorkflowStatus::factory()
        ->system('closed_won')
        ->create(['opportunity_workflow_id' => $workflow->id]);

    $opportunity = Opportunity::factory()->create([
        'source_id' => $source->id,
        'opportunity_workflow_status_id' => $openStatus->id,
    ]);

    Sanctum::actingAs($actor);

    $this->postJson('/api/tables/opportunity-workflows/bulk-delete', ['ids' => [$workflow->id]])->assertOk();

    $this->assertDatabaseMissing('opportunity_workflows', ['id' => $workflow->id]);

    $opportunity->refresh();
    expect($opportunity->opportunity_workflow_status_id)->not->toBeNull();

    $globalOpen = OpportunityWorkflowStatus::whereNull('opportunity_workflow_id')->where('system_key', 'open')->sole();
    expect($opportunity->opportunity_workflow_status_id)->toBe($globalOpen->id);
});

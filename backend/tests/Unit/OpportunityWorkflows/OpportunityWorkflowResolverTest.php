<?php

use App\Models\BusinessFunction;
use App\Models\Opportunity;
use App\Models\OpportunityWorkflow;
use App\Models\OpportunityWorkflowStatus;
use App\Models\ProductCategory;
use App\Models\Source;
use App\Models\State;
use App\Services\Opportunities\OpportunityWorkflowResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Touches the database (workflows/criteria/statuses + Opportunity), so bind
// the full TestCase + RefreshDatabase explicitly (Unit suite has no default
// RefreshDatabase binding), mirroring Foundation0047Test.
uses(TestCase::class, RefreshDatabase::class);

if (! function_exists('workflowWithSystemStatuses')) {
    /**
     * A workflow carrying its own pinned open/closed_won/closed_lost system
     * rows (AC-004, normally created by the Lane A configurator service — not
     * built by this lane — so tests build them directly via the factory,
     * mirroring the migration's regenerated system-row shape).
     *
     * @param  array<string, mixed>  $attributes
     */
    function workflowWithSystemStatuses(array $attributes = []): OpportunityWorkflow
    {
        $workflow = OpportunityWorkflow::factory()->create($attributes);

        foreach (['open', 'closed_won', 'closed_lost'] as $key) {
            OpportunityWorkflowStatus::factory()
                ->system($key)
                ->create(['opportunity_workflow_id' => $workflow->id]);
        }

        return $workflow;
    }
}

if (! function_exists('workflowResolver')) {
    function workflowResolver(): OpportunityWorkflowResolver
    {
        return app(OpportunityWorkflowResolver::class);
    }
}

// ---------------------------------------------------------------------------
// resolve() — AC-010/AC-011/AC-012/AC-013/AC-014
// ---------------------------------------------------------------------------

it('resolves to the global default set when no active workflow matches (AC-010)', function () {
    $opportunity = Opportunity::factory()->create(['source_id' => null, 'state_id' => null]);
    $opportunity->load('productLines');

    $workflow = workflowResolver()->resolve($opportunity);

    expect($workflow)->toBeNull();

    $target = workflowResolver()->targetStatus($opportunity, $workflow);

    expect($target->opportunity_workflow_id)->toBeNull()
        ->and($target->system_key)->toBe('open');
});

it('picks the more specific of two matching workflows: 2 criteria beats 1 (AC-011)', function () {
    $source = Source::factory()->create();
    $state = State::factory()->create();

    $opportunity = Opportunity::factory()->create(['source_id' => $source->id, 'state_id' => $state->id]);
    $opportunity->load('productLines');

    $lessSpecific = workflowWithSystemStatuses();
    $lessSpecific->criteria()->create(['field' => 'source_id', 'value_id' => $source->id]);

    $moreSpecific = workflowWithSystemStatuses();
    $moreSpecific->criteria()->create(['field' => 'source_id', 'value_id' => $source->id]);
    $moreSpecific->criteria()->create(['field' => 'state_id', 'value_id' => $state->id]);

    $resolved = workflowResolver()->resolve($opportunity);

    expect($resolved)->not->toBeNull()
        ->and($resolved->id)->toBe($moreSpecific->id);
});

it('tie-breaks equal specificity by id asc (AC-012)', function () {
    $source = Source::factory()->create();
    $opportunity = Opportunity::factory()->create(['source_id' => $source->id]);
    $opportunity->load('productLines');

    $first = workflowWithSystemStatuses();
    $first->criteria()->create(['field' => 'source_id', 'value_id' => $source->id]);

    $second = workflowWithSystemStatuses();
    $second->criteria()->create(['field' => 'source_id', 'value_id' => $source->id]);

    $resolved = workflowResolver()->resolve($opportunity);

    expect($resolved->id)->toBe(min($first->id, $second->id));
});

it('matches business_function_id/product_category_id against ANY product line row, AND across criteria (AC-013)', function () {
    $functionA = BusinessFunction::factory()->create();
    $categoryA = ProductCategory::factory()->create(['business_function_id' => $functionA->id]);
    $functionB = BusinessFunction::factory()->create();
    $categoryB = ProductCategory::factory()->create(['business_function_id' => $functionB->id]);

    $opportunity = Opportunity::factory()->create();
    $opportunity->productLines()->create(['business_function_id' => $functionA->id, 'product_category_id' => $categoryA->id]);
    $opportunity->productLines()->create(['business_function_id' => $functionB->id, 'product_category_id' => $categoryB->id]);
    $opportunity->load('productLines');

    $matching = workflowWithSystemStatuses();
    $matching->criteria()->create(['field' => 'business_function_id', 'value_id' => $functionA->id]);
    $matching->criteria()->create(['field' => 'product_category_id', 'value_id' => $categoryB->id]);

    expect(workflowResolver()->resolve($opportunity)->id)->toBe($matching->id);

    // A criterion whose value is not present on ANY product line row never
    // matches, even though the OTHER criterion (product_category_id) would.
    $otherFunction = BusinessFunction::factory()->create();
    $nonMatching = workflowWithSystemStatuses();
    $nonMatching->criteria()->create(['field' => 'business_function_id', 'value_id' => $otherFunction->id]);
    $nonMatching->criteria()->create(['field' => 'product_category_id', 'value_id' => $categoryB->id]);

    $opportunity->load('productLines');
    expect(workflowResolver()->resolve($opportunity)->id)->toBe($matching->id);
});

it('ignores an inactive workflow (AC-014)', function () {
    $source = Source::factory()->create();
    $opportunity = Opportunity::factory()->create(['source_id' => $source->id]);
    $opportunity->load('productLines');

    $inactive = workflowWithSystemStatuses(['is_active' => false]);
    $inactive->criteria()->create(['field' => 'source_id', 'value_id' => $source->id]);

    expect(workflowResolver()->resolve($opportunity))->toBeNull();
});

// ---------------------------------------------------------------------------
// targetStatus() — D3
// ---------------------------------------------------------------------------

it('targetStatus keeps the current status when it already belongs to the resolved set', function () {
    $workflow = workflowWithSystemStatuses();
    $custom = OpportunityWorkflowStatus::factory()->create([
        'opportunity_workflow_id' => $workflow->id,
        'system_key' => null,
        'sort_order' => 5,
    ]);

    $opportunity = Opportunity::factory()->create(['opportunity_workflow_status_id' => $custom->id]);

    $target = workflowResolver()->targetStatus($opportunity, $workflow);

    expect($target->id)->toBe($custom->id);
});

it('re-maps by system_key when the resolved set changes and the current status does not belong to it (AC-016)', function () {
    $oldWorkflow = workflowWithSystemStatuses();
    $newWorkflow = workflowWithSystemStatuses();

    $oldClosed = $oldWorkflow->statuses()->where('system_key', 'closed_won')->sole();
    $opportunity = Opportunity::factory()->create(['opportunity_workflow_status_id' => $oldClosed->id]);

    $target = workflowResolver()->targetStatus($opportunity, $newWorkflow);

    $newClosed = $newWorkflow->statuses()->where('system_key', 'closed_won')->sole();
    expect($target->id)->toBe($newClosed->id);
});

it('falls back to the open row when the current status is custom and does not belong to the new set', function () {
    $oldWorkflow = workflowWithSystemStatuses();
    $customOld = OpportunityWorkflowStatus::factory()->create([
        'opportunity_workflow_id' => $oldWorkflow->id,
        'system_key' => null,
    ]);
    $newWorkflow = workflowWithSystemStatuses();

    $opportunity = Opportunity::factory()->create(['opportunity_workflow_status_id' => $customOld->id]);

    $target = workflowResolver()->targetStatus($opportunity, $newWorkflow);

    $newOpen = $newWorkflow->statuses()->where('system_key', 'open')->sole();
    expect($target->id)->toBe($newOpen->id);
});

it('falls back to the set open row when the opportunity has no current status', function () {
    $workflow = workflowWithSystemStatuses();
    $opportunity = Opportunity::factory()->create(['opportunity_workflow_status_id' => null]);

    $target = workflowResolver()->targetStatus($opportunity, $workflow);
    $open = $workflow->statuses()->where('system_key', 'open')->sole();

    expect($target->id)->toBe($open->id);
});

// ---------------------------------------------------------------------------
// resolveAndAssign()
// ---------------------------------------------------------------------------

it('resolveAndAssign() persists only opportunity_workflow_status_id, to the global open row absent any match', function () {
    $opportunity = Opportunity::factory()->create(['opportunity_workflow_status_id' => null]);
    $opportunity->load('productLines');

    workflowResolver()->resolveAndAssign($opportunity);

    $globalOpen = OpportunityWorkflowStatus::query()
        ->whereNull('opportunity_workflow_id')
        ->where('system_key', 'open')
        ->sole();

    expect($opportunity->opportunity_workflow_status_id)->toBe($globalOpen->id);
    $this->assertDatabaseHas('opportunities', [
        'id' => $opportunity->id,
        'opportunity_workflow_status_id' => $globalOpen->id,
    ]);
});

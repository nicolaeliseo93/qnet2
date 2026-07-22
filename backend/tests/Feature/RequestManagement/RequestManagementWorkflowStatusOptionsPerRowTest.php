<?php

use App\Models\BusinessFunction;
use App\Models\Opportunity;
use App\Models\OpportunityWorkflow;
use App\Models\OpportunityWorkflowCriterion;
use App\Models\OpportunityWorkflowStatus;
use App\Models\ProductCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

// GET (POST) /api/tables/request-management/rows — per-row
// `workflow_status_options` (spec 0054 follow-up: the operator should not
// see, in the dropdown, a status the server would then reject with 422).
// Verifies the memoization in OpportunityWorkflowResolver keeps this
// addition query-bounded (not proportional to the row count).

uses(RefreshDatabase::class);

if (! function_exists('workflowOptionsActor')) {
    function workflowOptionsActor(): User
    {
        Permission::findOrCreate('request-management.viewAny');
        Permission::findOrCreate('request-management.viewAll');

        $user = User::factory()->create();
        $user->givePermissionTo(['request-management.viewAny', 'request-management.viewAll']);

        return $user;
    }
}

/**
 * A query-count listener scoped to SELECTs against the two tables spec
 * 0047's workflow resolution reads (`opportunity_workflows`/
 * `opportunity_workflow_statuses`) — matched on the FROM clause, never a
 * bare substring: `opportunities` INSERTs also mention the COLUMN name
 * `opportunity_workflow_status_id` in their own SQL text, which a plain
 * `str_contains` would wrongly count.
 */
if (! function_exists('countWorkflowQueries')) {
    function countWorkflowQueries(Closure $callback): int
    {
        $count = 0;

        $listener = function ($query) use (&$count): void {
            if (preg_match('/from ["`]?opportunity_workflow/i', $query->sql) === 1) {
                $count++;
            }
        };

        DB::listen($listener);
        $callback();

        return $count;
    }
}

it('workflow_status_options rides along on every row, matching the resolved workflow\'s set', function () {
    $actor = workflowOptionsActor();
    $opportunity = Opportunity::factory()->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/request-management/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();

    $row = collect($response->json('items'))->firstWhere('id', $opportunity->id);
    $globalStatusIds = OpportunityWorkflowStatus::query()->whereNull('opportunity_workflow_id')->orderBy('sort_order')->pluck('id')->all();

    expect($row['workflow_status_options'])->toBe($globalStatusIds);
});

it('resolving workflow_status_options for a page of rows costs the SAME number of queries regardless of row count', function () {
    // The rigorous form of "not one per row": compare two page sizes and
    // require an IDENTICAL query count. An absolute upper bound would be
    // brittle against unrelated, already-constant costs (e.g. the advanced
    // filter catalogue's own distinct-values query) — this instead proves
    // the ONE property that actually matters: O(1), not O(n).
    $actor = workflowOptionsActor();

    Opportunity::factory()->count(3)->create();
    Sanctum::actingAs($actor);
    $smallPageQueries = countWorkflowQueries(function (): void {
        $this->postJson('/api/tables/request-management/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
    });

    Opportunity::factory()->count(12)->create();
    $largePageQueries = countWorkflowQueries(function (): void {
        $this->postJson('/api/tables/request-management/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
    });

    expect($largePageQueries)->toBe($smallPageQueries);
});

/**
 * @return array{businessFunction: BusinessFunction, workflow: OpportunityWorkflow}
 */
if (! function_exists('customMatchedWorkflow')) {
    function customMatchedWorkflow(): array
    {
        $businessFunction = BusinessFunction::factory()->create();
        $workflow = OpportunityWorkflow::factory()->create(['is_active' => true]);
        OpportunityWorkflowCriterion::query()->create([
            'opportunity_workflow_id' => $workflow->id,
            'field' => 'business_function_id',
            'value_id' => $businessFunction->id,
        ]);
        OpportunityWorkflowStatus::factory()->create(['opportunity_workflow_id' => $workflow->id, 'system_key' => 'open', 'sort_order' => 1]);
        OpportunityWorkflowStatus::factory()->create(['opportunity_workflow_id' => $workflow->id, 'system_key' => 'closed_won', 'sort_order' => 2]);
        OpportunityWorkflowStatus::factory()->create(['opportunity_workflow_id' => $workflow->id, 'system_key' => 'closed_lost', 'sort_order' => 3]);

        return ['businessFunction' => $businessFunction, 'workflow' => $workflow];
    }
}

/**
 * $count opportunities matching $businessFunction's custom workflow (product
 * line -> business function criterion).
 */
if (! function_exists('createMatchingOpportunities')) {
    function createMatchingOpportunities(BusinessFunction $businessFunction, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $matching = Opportunity::factory()->create();
            $matching->productLines()->create([
                'business_function_id' => $businessFunction->id,
                'product_category_id' => ProductCategory::factory()->create(['business_function_id' => $businessFunction->id])->id,
            ]);
        }
    }
}

it('a page mixing rows across several DISTINCT resolved workflows costs the SAME number of queries as the row count grows', function () {
    // Same rigor as above: 2 distinct resolved sets (the custom workflow +
    // the global default) across a SMALL page, then the SAME 2 sets across a
    // LARGER page — an identical query count proves the cost scales with the
    // number of DISTINCT workflows encountered, never with the row count.
    $actor = workflowOptionsActor();
    ['businessFunction' => $businessFunction] = customMatchedWorkflow();

    createMatchingOpportunities($businessFunction, 2);
    Opportunity::factory()->count(2)->create(); // falls back to the global default
    Sanctum::actingAs($actor);
    $smallPageQueries = countWorkflowQueries(function (): void {
        $this->postJson('/api/tables/request-management/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
    });

    createMatchingOpportunities($businessFunction, 6);
    Opportunity::factory()->count(6)->create();
    $largePageQueries = countWorkflowQueries(function (): void {
        $this->postJson('/api/tables/request-management/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
    });

    expect($largePageQueries)->toBe($smallPageQueries);
});

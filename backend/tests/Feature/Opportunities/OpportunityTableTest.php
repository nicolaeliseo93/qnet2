<?php

use App\Jobs\GenerateExportJob;
use App\Models\ExportRun;
use App\Models\Opportunity;
use App\Models\Referent;
use App\Models\Registry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('opportunityTableUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function opportunityTableUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("opportunities.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("opportunities.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// AC-040 — columns config
// ---------------------------------------------------------------------------

it('GET /api/tables/opportunities/columns: 200 with the declared columns, 403 without viewAny (AC-040)', function () {
    $actor = opportunityTableUserWith([]);
    Sanctum::actingAs($actor);
    $this->getJson('/api/tables/opportunities/columns')->assertForbidden();

    $actor = opportunityTableUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $data = $this->getJson('/api/tables/opportunities/columns')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->json('data');

    expect($data['resource'])->toBe('opportunities')
        ->and($data['defaultSort'])->toBe([['columnId' => 'created_at', 'direction' => 'desc']]);

    $ids = collect($data['columns'])->pluck('id')->all();
    expect($ids)->toBe([
        'name', 'registry', 'referent', 'commercial', 'supervisor', 'source', 'product_category',
        'estimated_value', 'success_probability', 'start_date', 'expected_close_date', 'created_at',
    ]);

    $columns = collect($data['columns'])->keyBy('id');
    expect($columns['registry']['sortable'])->toBeTrue()
        ->and($columns['registry']['filterType'])->toBe('set')
        ->and($columns['estimated_value']['filterType'])->toBe('number');
});

// ---------------------------------------------------------------------------
// AC-041 — filter on the registry column applied to the ENTIRE dataset;
// sort on a derived column via allow-list subquery
// ---------------------------------------------------------------------------

it('rows: a filter on the registry column returns only that registry\'s opportunities (AC-041)', function () {
    $actor = opportunityTableUserWith(['viewAny']);
    $registry = Registry::factory()->create(['name' => 'Match Registry']);
    $otherRegistry = Registry::factory()->create(['name' => 'Other Registry']);
    $matching = Opportunity::factory()->create(['registry_id' => $registry->id]);
    Opportunity::factory()->create(['registry_id' => $otherRegistry->id]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/opportunities/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'filterModel' => ['registry' => ['filterType' => 'set', 'values' => ['Match Registry']]],
    ])->assertOk();

    $ids = collect($response->json('items'))->pluck('id');
    expect($ids->all())->toBe([$matching->id]);
});

it('rows: sorting by referent orders opportunities by the related referent name via allow-list subquery (AC-041)', function () {
    $actor = opportunityTableUserWith(['viewAny']);
    $referentAlpha = Referent::factory()->create(['name' => 'Alpha Referent']);
    $referentZulu = Referent::factory()->create(['name' => 'Zulu Referent']);
    $opportunityZulu = Opportunity::factory()->create(['referent_id' => $referentZulu->id]);
    $opportunityAlpha = Opportunity::factory()->create(['referent_id' => $referentAlpha->id]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/opportunities/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'sortModel' => [['colId' => 'referent', 'sort' => 'asc']],
    ])->assertOk();

    $ids = collect($response->json('items'))->pluck('id');
    expect($ids->all())->toBe([$opportunityAlpha->id, $opportunityZulu->id]);
});

it('rows: an unknown sort column is rejected (allow-list, no raw SQL escape hatch)', function () {
    $actor = opportunityTableUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/tables/opportunities/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'sortModel' => [['colId' => 'lead_id', 'sort' => 'asc']],
    ])->assertStatus(422);
});

it('rows: registry/referent/commercial/supervisor/source/product_category surface as {id, name} summaries', function () {
    $actor = opportunityTableUserWith(['viewAny']);
    $registry = Registry::factory()->create(['name' => 'Acme Spa']);
    $opportunity = Opportunity::factory()->create(['registry_id' => $registry->id]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/opportunities/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
    $row = collect($response->json('items'))->firstWhere('id', $opportunity->id);

    expect($row['registry'])->toMatchArray(['id' => $registry->id, 'name' => 'Acme Spa']);
    expect($row['referent'])->toBeNull();
});

// ---------------------------------------------------------------------------
// AC-042 — export: created with opportunities.export, 403 without it
// ---------------------------------------------------------------------------

if (! function_exists('opportunityExportPayload')) {
    /**
     * @return array<string, mixed>
     */
    function opportunityExportPayload(): array
    {
        return [
            'format' => 'csv',
            'columns' => [
                ['colId' => 'name', 'header' => 'Name'],
                ['colId' => 'registry', 'header' => 'Registry'],
            ],
        ];
    }
}

it('201 creates the ExportRun and dispatches GenerateExportJob with opportunities.export (AC-042)', function () {
    Queue::fake();
    $actor = opportunityTableUserWith(['export']);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/exports/opportunities', opportunityExportPayload())
        ->assertCreated()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.export_run.status', 'processing')
        ->assertJsonPath('data.export_run.resource', 'opportunities')
        ->assertJsonPath('data.export_run.format', 'csv');

    $run = ExportRun::findOrFail($response->json('data.export_run.id'));
    expect($run->user_id)->toBe($actor->id);

    Queue::assertPushed(GenerateExportJob::class);
});

it('403 without opportunities.export, no ExportRun created (AC-042)', function () {
    Queue::fake();
    $actor = opportunityTableUserWith([]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/exports/opportunities', opportunityExportPayload())->assertForbidden();

    expect(ExportRun::count())->toBe(0);
    Queue::assertNotPushed(GenerateExportJob::class);
});

<?php

use App\Jobs\GenerateExportJob;
use App\Models\OpportunityStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('opportunityStatusUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function opportunityStatusUserWith(array $abilities): User
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
// AC-009 — columns config
// ---------------------------------------------------------------------------

it('GET /api/tables/opportunity-statuses/columns: 200 with the declared columns, 403 without viewAny', function () {
    $actor = opportunityStatusUserWith([]);
    Sanctum::actingAs($actor);
    $this->getJson('/api/tables/opportunity-statuses/columns')->assertForbidden();

    $actor = opportunityStatusUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $data = $this->getJson('/api/tables/opportunity-statuses/columns')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->json('data');

    expect($data['resource'])->toBe('opportunity-statuses')
        ->and($data['defaultSort'])->toBe([['columnId' => 'sort_order', 'direction' => 'asc']])
        ->and($data['searchable'])->toBe(['name']);

    $ids = collect($data['columns'])->pluck('id')->all();
    expect($ids)->toBe(['id', 'name', 'color', 'sort_order', 'group', 'created_at']);

    $columns = collect($data['columns'])->keyBy('id');
    expect($columns['id']['sortable'])->toBeTrue()
        ->and($columns['id']['filterable'])->toBeFalse()
        ->and($columns['id']['filterType'])->toBeNull()
        ->and($columns['id']['type'])->toBe('number')
        ->and($columns['id']['visible'])->toBeFalse()
        ->and($columns['name']['sortable'])->toBeTrue()
        ->and($columns['name']['filterable'])->toBeTrue()
        ->and($columns['color']['sortable'])->toBeFalse()
        ->and($columns['color']['filterable'])->toBeFalse()
        ->and($columns['sort_order']['sortable'])->toBeTrue()
        ->and($columns['sort_order']['filterType'])->toBe('number');
});

// ---------------------------------------------------------------------------
// AC-009 — rows shape + export + no delete on a system row
// ---------------------------------------------------------------------------

it('rows expose name/color/sort_order/group/created_at + per-row actions, no delete on a system row (AC-009)', function () {
    $actor = opportunityStatusUserWith(['viewAny', 'view', 'update', 'delete']);
    OpportunityStatus::factory()->create(['name' => 'Attiva', 'color' => 'red', 'sort_order' => 1]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/opportunity-statuses/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
    $row = collect($response->json('items'))->firstWhere('name', 'Attiva');

    expect($row)->not->toBeNull()
        ->and($row['color'])->toBe('red')
        ->and($row['sort_order'])->toBe(1)
        ->and($row['actions'])->toEqualCanonicalizing(['view', 'edit', 'delete']);

    $newRow = collect($response->json('items'))->firstWhere('system_key', 'new');
    expect($newRow)->not->toBeNull()
        ->and($newRow['actions'])->not->toContain('delete');
});

it('export: the opportunity-statuses domain is registered in the generic export engine (AC-009, export_is_free)', function () {
    Queue::fake();
    $actor = opportunityStatusUserWith(['export']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/exports/opportunity-statuses', [
        'format' => 'csv',
        'columns' => [['colId' => 'name', 'header' => 'Name']],
    ])
        ->assertCreated()
        ->assertJsonPath('data.export_run.resource', 'opportunity-statuses');

    Queue::assertPushed(GenerateExportJob::class);
});

it('export: 403 without opportunity-statuses.export, no ExportRun created', function () {
    Queue::fake();
    $actor = opportunityStatusUserWith([]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/exports/opportunity-statuses', [
        'format' => 'csv',
        'columns' => [['colId' => 'name', 'header' => 'Name']],
    ])->assertForbidden();

    Queue::assertNotPushed(GenerateExportJob::class);
});

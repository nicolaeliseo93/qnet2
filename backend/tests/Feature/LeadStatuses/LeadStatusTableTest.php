<?php

use App\Jobs\GenerateExportJob;
use App\Models\LeadStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('leadStatusUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function leadStatusUserWith(array $abilities): User
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
// AC-009 — columns config
// ---------------------------------------------------------------------------

it('GET /api/tables/lead-statuses/columns: 200 with the declared columns, 403 without viewAny', function () {
    $actor = leadStatusUserWith([]);
    Sanctum::actingAs($actor);
    $this->getJson('/api/tables/lead-statuses/columns')->assertForbidden();

    $actor = leadStatusUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $data = $this->getJson('/api/tables/lead-statuses/columns')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->json('data');

    expect($data['resource'])->toBe('lead-statuses')
        ->and($data['defaultSort'])->toBe([['columnId' => 'sort_order', 'direction' => 'asc']])
        ->and($data['searchable'])->toBe(['name']);

    // requirement changed (spec 0039, D-6/D-7): `status_group` is a new
    // derived column between `sort_order` and `created_at`.
    $ids = collect($data['columns'])->pluck('id')->all();
    expect($ids)->toBe(['name', 'color', 'sort_order', 'status_group', 'created_at']);

    $columns = collect($data['columns'])->keyBy('id');
    expect($columns['name']['sortable'])->toBeTrue()
        ->and($columns['name']['filterable'])->toBeTrue()
        ->and($columns['color']['sortable'])->toBeFalse()
        ->and($columns['color']['filterable'])->toBeFalse()
        ->and($columns['sort_order']['sortable'])->toBeTrue()
        ->and($columns['sort_order']['filterType'])->toBe('number');
});

// ---------------------------------------------------------------------------
// AC-009 — rows shape + export
// ---------------------------------------------------------------------------

it('rows expose name/color/sort_order/created_at + per-row actions', function () {
    $actor = leadStatusUserWith(['viewAny', 'view', 'update', 'delete']);
    LeadStatus::factory()->create(['name' => 'Attivo', 'color' => 'red', 'sort_order' => 1]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/lead-statuses/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
    $row = collect($response->json('items'))->firstWhere('name', 'Attivo');

    expect($row)->not->toBeNull()
        ->and($row['color'])->toBe('red')
        ->and($row['sort_order'])->toBe(1)
        ->and($row['actions'])->toEqualCanonicalizing(['view', 'edit', 'delete']);
});

it('export: the lead-statuses domain is registered in the generic export engine (AC-009, export_is_free)', function () {
    Queue::fake();
    $actor = leadStatusUserWith(['export']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/exports/lead-statuses', [
        'format' => 'csv',
        'columns' => [['colId' => 'name', 'header' => 'Name']],
    ])
        ->assertCreated()
        ->assertJsonPath('data.export_run.resource', 'lead-statuses');

    Queue::assertPushed(GenerateExportJob::class);
});

it('export: 403 without lead-statuses.export, no ExportRun created', function () {
    Queue::fake();
    $actor = leadStatusUserWith([]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/exports/lead-statuses', [
        'format' => 'csv',
        'columns' => [['colId' => 'name', 'header' => 'Name']],
    ])->assertForbidden();

    Queue::assertNotPushed(GenerateExportJob::class);
});

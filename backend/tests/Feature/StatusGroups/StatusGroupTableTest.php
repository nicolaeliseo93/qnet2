<?php

use App\Jobs\GenerateExportJob;
use App\Models\StatusGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('statusGroupUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function statusGroupUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("status-groups.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("status-groups.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// columns config
// ---------------------------------------------------------------------------

it('GET /api/tables/status-groups/columns: 200 with the declared columns, 403 without viewAny', function () {
    $actor = statusGroupUserWith([]);
    Sanctum::actingAs($actor);
    $this->getJson('/api/tables/status-groups/columns')->assertForbidden();

    $actor = statusGroupUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $data = $this->getJson('/api/tables/status-groups/columns')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->json('data');

    expect($data['resource'])->toBe('status-groups')
        ->and($data['defaultSort'])->toBe([['columnId' => 'sort_order', 'direction' => 'asc']])
        ->and($data['searchable'])->toBe(['name']);

    $ids = collect($data['columns'])->pluck('id')->all();
    expect($ids)->toBe(['name', 'color', 'sort_order', 'created_at']);

    $columns = collect($data['columns'])->keyBy('id');
    expect($columns['name']['sortable'])->toBeTrue()
        ->and($columns['name']['filterable'])->toBeTrue()
        ->and($columns['color']['sortable'])->toBeFalse()
        ->and($columns['color']['filterable'])->toBeFalse()
        ->and($columns['sort_order']['sortable'])->toBeTrue()
        ->and($columns['sort_order']['filterType'])->toBe('number');
});

// ---------------------------------------------------------------------------
// rows shape + export
// ---------------------------------------------------------------------------

it('rows expose name/color/sort_order/created_at + per-row actions', function () {
    $actor = statusGroupUserWith(['viewAny', 'view', 'update', 'delete']);
    StatusGroup::factory()->create(['name' => 'Attivo', 'color' => 'red', 'sort_order' => 1]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/status-groups/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
    $row = collect($response->json('items'))->firstWhere('name', 'Attivo');

    expect($row)->not->toBeNull()
        ->and($row['color'])->toBe('red')
        ->and($row['sort_order'])->toBe(1)
        ->and($row['actions'])->toEqualCanonicalizing(['view', 'edit', 'delete']);
});

it('export: the status-groups domain is registered in the generic export engine (export_is_free)', function () {
    Queue::fake();
    $actor = statusGroupUserWith(['export']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/exports/status-groups', [
        'format' => 'csv',
        'columns' => [['colId' => 'name', 'header' => 'Name']],
    ])
        ->assertCreated()
        ->assertJsonPath('data.export_run.resource', 'status-groups');

    Queue::assertPushed(GenerateExportJob::class);
});

it('export: 403 without status-groups.export, no ExportRun created', function () {
    Queue::fake();
    $actor = statusGroupUserWith([]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/exports/status-groups', [
        'format' => 'csv',
        'columns' => [['colId' => 'name', 'header' => 'Name']],
    ])->assertForbidden();

    Queue::assertNotPushed(GenerateExportJob::class);
});

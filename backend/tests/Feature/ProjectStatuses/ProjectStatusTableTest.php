<?php

use App\Models\ProjectStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('projectStatusUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function projectStatusUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("project-statuses.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("project-statuses.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// AC-030 — columns config
// ---------------------------------------------------------------------------

it('GET /api/tables/project-statuses/columns: 200 with the declared columns, 403 without viewAny', function () {
    $actor = projectStatusUserWith([]);
    Sanctum::actingAs($actor);
    $this->getJson('/api/tables/project-statuses/columns')->assertForbidden();

    $actor = projectStatusUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $data = $this->getJson('/api/tables/project-statuses/columns')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->json('data');

    expect($data['resource'])->toBe('project-statuses')
        ->and($data['defaultSort'])->toBe([['columnId' => 'sort_order', 'direction' => 'asc']])
        ->and($data['searchable'])->toBe(['name']);

    $ids = collect($data['columns'])->pluck('id')->all();
    expect($ids)->toBe(['name', 'color', 'sort_order', 'created_at']);

    $columns = collect($data['columns'])->keyBy('id');
    expect($columns['name']['sortable'])->toBeTrue()
        ->and($columns['color']['sortable'])->toBeFalse()
        ->and($columns['color']['filterable'])->toBeFalse()
        ->and($columns['sort_order']['filterType'])->toBe('number');
});

// ---------------------------------------------------------------------------
// AC-030 — rows shape
// ---------------------------------------------------------------------------

it('rows expose name/color/sort_order/created_at + per-row actions', function () {
    $actor = projectStatusUserWith(['viewAny', 'view', 'update', 'delete']);
    ProjectStatus::factory()->create(['name' => 'Attivo', 'color' => '#ff0000', 'sort_order' => 1]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/project-statuses/rows', ['startRow' => 0, 'endRow' => 25])->assertOk();
    $row = collect($response->json('items'))->firstWhere('name', 'Attivo');

    expect($row)->not->toBeNull()
        ->and($row['color'])->toBe('#ff0000')
        ->and($row['sort_order'])->toBe(1)
        ->and($row['actions'])->toEqualCanonicalizing(['view', 'edit', 'delete']);
});

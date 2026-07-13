<?php

use App\Models\Project;
use App\Models\ProjectStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('projectUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function projectUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("projects.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("projects.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// AC-030 — columns config
// ---------------------------------------------------------------------------

it('GET /api/tables/projects/columns: 200 with the declared columns, 403 without viewAny', function () {
    $actor = projectUserWith([]);
    Sanctum::actingAs($actor);
    $this->getJson('/api/tables/projects/columns')->assertForbidden();

    $actor = projectUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $data = $this->getJson('/api/tables/projects/columns')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->json('data');

    expect($data['resource'])->toBe('projects')
        ->and($data['defaultSort'])->toBe([['columnId' => 'created_at', 'direction' => 'desc']])
        ->and($data['searchable'])->toBe(['code', 'name']);

    $ids = collect($data['columns'])->pluck('id')->all();
    expect($ids)->toBe([
        'code', 'name', 'registry', 'project_status', 'source', 'business_function',
        'state', 'product_category', 'partner', 'start_date', 'end_date',
        'total_budget', 'target_lead', 'created_at',
    ]);

    $columns = collect($data['columns'])->keyBy('id');
    expect($columns['project_status']['sortable'])->toBeTrue()
        ->and($columns['project_status']['filterType'])->toBe('set')
        ->and($columns['registry']['sortable'])->toBeTrue()
        ->and($columns['source']['sortable'])->toBeFalse()
        ->and($columns['source']['filterType'])->toBe('set');
});

// ---------------------------------------------------------------------------
// AC-031 — sort on the derived project_status column
// ---------------------------------------------------------------------------

it('sorts rows by the derived project_status name via a correlated subquery (AC-031)', function () {
    $actor = projectUserWith(['viewAny']);
    $zed = ProjectStatus::factory()->create(['name' => 'Zed Status']);
    $amy = ProjectStatus::factory()->create(['name' => 'Amy Status']);
    Project::factory()->create(['name' => 'Z-project', 'project_status_id' => $zed->id]);
    Project::factory()->create(['name' => 'A-project', 'project_status_id' => $amy->id]);
    Sanctum::actingAs($actor);

    $names = $this->postJson('/api/tables/projects/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'sortModel' => [['colId' => 'project_status', 'sort' => 'asc']],
    ])->assertOk()->json('items.*.name');

    expect(array_search('A-project', $names, true))->toBeLessThan(array_search('Z-project', $names, true));
});

it('a sort colId outside the allow-list returns 422, never a 500 / raw SQL (AC-031)', function () {
    $actor = projectUserWith(['viewAny']);
    Project::factory()->count(2)->create();
    Sanctum::actingAs($actor);

    $this->postJson('/api/tables/projects/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'sortModel' => [['colId' => 'not_a_real_column; DROP TABLE projects;--', 'sort' => 'asc']],
    ])->assertStatus(422)->assertJsonValidationErrors('sortModel.0.colId');

    // Defence in depth: the table must still exist and be queryable.
    expect(Project::count())->toBe(2);
});

it('the derived project_status set filter matches by the related status name', function () {
    $actor = projectUserWith(['viewAny']);
    $commercial = ProjectStatus::factory()->create(['name' => 'Commercial']);
    $technical = ProjectStatus::factory()->create(['name' => 'Technical']);
    Project::factory()->create(['name' => 'Project A', 'project_status_id' => $commercial->id]);
    Project::factory()->create(['name' => 'Project B', 'project_status_id' => $technical->id]);
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/tables/projects/rows', [
        'startRow' => 0,
        'endRow' => 25,
        'filterModel' => ['project_status' => ['filterType' => 'set', 'values' => ['Commercial']]],
    ])->assertOk();

    $names = collect($response->json('items'))->pluck('name');
    expect($names->all())->toBe(['Project A']);
});

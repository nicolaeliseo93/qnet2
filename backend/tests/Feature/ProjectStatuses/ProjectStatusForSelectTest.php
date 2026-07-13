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
// AC-006 — auth + authorization
// ---------------------------------------------------------------------------

it('requires authentication (401)', function () {
    $this->getJson('/api/project-statuses/for-select')->assertUnauthorized();
});

it('forbids actors without project-statuses.viewAny (403)', function () {
    $actor = projectStatusUserWith([]);
    Sanctum::actingAs($actor);

    $this->getJson('/api/project-statuses/for-select')->assertForbidden();
});

it('allows actors with project-statuses.viewAny (200) and returns the paginated envelope', function () {
    $actor = projectStatusUserWith(['viewAny']);
    ProjectStatus::factory()->count(3)->create();
    Sanctum::actingAs($actor);

    $this->getJson('/api/project-statuses/for-select')
        ->assertOk()
        ->assertJsonStructure([
            'items' => [['id', 'label']],
            'export_link',
            'pagination' => ['total', 'offset', 'limit', 'total_pages'],
        ]);
});

// ---------------------------------------------------------------------------
// AC-006 — item shape + search
// ---------------------------------------------------------------------------

it('maps a project status to { id, label: name }', function () {
    $actor = projectStatusUserWith(['viewAny']);
    $target = ProjectStatus::factory()->create(['name' => 'In Progress']);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/project-statuses/for-select?search=In Progress')->assertOk();
    $item = collect($response->json('items'))->firstWhere('id', $target->id);

    expect($item)->toMatchArray(['id' => $target->id, 'label' => 'In Progress'])
        ->and(array_keys($item))->toEqualCanonicalizing(['id', 'label']);
});

it('search="boz" returns only names containing "boz" (AC-006)', function () {
    $actor = projectStatusUserWith(['viewAny']);
    $match = ProjectStatus::factory()->create(['name' => 'Bozza']);
    ProjectStatus::factory()->create(['name' => 'Completato']);
    Sanctum::actingAs($actor);

    $response = $this->getJson('/api/project-statuses/for-select?search=boz')->assertOk();

    expect($response->json('pagination.total'))->toBe(1)
        ->and($response->json('items.0.id'))->toBe($match->id);
});

// ---------------------------------------------------------------------------
// AC-006 — ids[] hydration + pagination
// ---------------------------------------------------------------------------

it('appends ids[] even when filtered out by search and does NOT inflate total', function () {
    $actor = projectStatusUserWith(['viewAny']);
    $searchMatch = ProjectStatus::factory()->create(['name' => 'Zephyr Searchable']);
    $selected = ProjectStatus::factory()->create(['name' => 'Quentin Selected']);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/project-statuses/for-select?search=Zephyr&ids[]={$selected->id}")->assertOk();
    $ids = collect($response->json('items'))->pluck('id');

    expect($ids)->toContain($searchMatch->id)
        ->and($ids)->toContain($selected->id)
        ->and($response->json('pagination.total'))->toBe(1);
});

it('rejects a limit above 100 (422)', function () {
    $actor = projectStatusUserWith(['viewAny']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/project-statuses/for-select?limit=101')
        ->assertStatus(422)->assertJsonValidationErrors('limit');
});

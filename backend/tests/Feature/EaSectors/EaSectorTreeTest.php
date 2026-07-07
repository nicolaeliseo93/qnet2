<?php

use App\Models\EaSector;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('eaSectorUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function eaSectorUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("ea-sectors.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("ea-sectors.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// AC-011 — GET /api/ea-sectors/tree
// ---------------------------------------------------------------------------

it('tree: 403 without ea-sectors.viewAny', function () {
    $actor = eaSectorUserWith([]);
    Sanctum::actingAs($actor);

    $this->getJson('/api/ea-sectors/tree')->assertForbidden();
});

it('tree: 200, nested roots→children at multiple levels, ordered by name', function () {
    $actor = eaSectorUserWith(['viewAny']);
    $zebraRoot = EaSector::factory()->create(['name' => 'Zebra Root']);
    $alphaRoot = EaSector::factory()->create(['name' => 'Alpha Root']);
    $child = EaSector::factory()->childOf($alphaRoot)->create(['name' => 'Child']);
    $grandchild = EaSector::factory()->childOf($child)->create(['name' => 'Grandchild']);
    Sanctum::actingAs($actor);

    $data = $this->getJson('/api/ea-sectors/tree')->assertOk()->json('data');

    expect(collect($data)->pluck('name')->all())->toBe(['Alpha Root', 'Zebra Root']);

    $alphaNode = collect($data)->firstWhere('name', 'Alpha Root');
    expect($alphaNode['id'])->toBe($alphaRoot->id)
        ->and($alphaNode['parent_id'])->toBeNull();

    $childNode = collect($alphaNode['children'])->firstWhere('name', 'Child');
    expect($childNode)->not->toBeNull()
        ->and($childNode['parent_id'])->toBe($alphaRoot->id);

    $grandchildNode = collect($childNode['children'])->firstWhere('name', 'Grandchild');
    expect($grandchildNode)->not->toBeNull()
        ->and($grandchildNode['parent_id'])->toBe($child->id)
        ->and($grandchildNode['children'])->toBe([]);

    $zebraNode = collect($data)->firstWhere('name', 'Zebra Root');
    expect($zebraNode['children'])->toBe([]);
});

<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('productsNavigationGroup')) {
    /**
     * @param  array<int, array<string, mixed>>  $data
     * @return array<string, mixed>|null
     */
    function productsNavigationGroup(array $data): ?array
    {
        $settings = collect($data)->firstWhere('key', 'settings');

        return collect(data_get($settings, 'children', []))->firstWhere('key', 'products-group');
    }
}

// ---------------------------------------------------------------------------
// AC-020 — permissions:sync creates the 7 standard permissions
// ---------------------------------------------------------------------------

it('permissions:sync creates all 7 product-categories.* permissions', function () {
    $this->artisan('permissions:sync')->assertSuccessful();

    foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
        expect(Permission::where('name', "product-categories.{$ability}")->exists())->toBeTrue();
    }
});

// ---------------------------------------------------------------------------
// AC-020 — navigation node gated by product-categories.view
// ---------------------------------------------------------------------------

it('navigation: the product-categories node only shows with product-categories.view', function () {
    Permission::findOrCreate('product-categories.view');

    $withoutView = User::factory()->create();
    Sanctum::actingAs($withoutView);
    $group = productsNavigationGroup($this->getJson('/api/navigation')->json('data'));
    expect(collect(data_get($group, 'children', []))->pluck('key'))->not->toContain('product-categories');

    $withView = User::factory()->create();
    $withView->givePermissionTo('product-categories.view');
    Sanctum::actingAs($withView);
    $group = productsNavigationGroup($this->getJson('/api/navigation')->json('data'));
    expect(collect(data_get($group, 'children', []))->pluck('key'))->toContain('product-categories');
});

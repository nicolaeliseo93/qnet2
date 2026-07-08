<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

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
    expect(navigationSectionKeys($this->getJson('/api/navigation')->json('data'), 'configuration'))
        ->not->toContain('product-categories');

    $withView = User::factory()->create();
    $withView->givePermissionTo('product-categories.view');
    Sanctum::actingAs($withView);
    expect(navigationSectionKeys($this->getJson('/api/navigation')->json('data'), 'configuration'))
        ->toContain('product-categories');
});

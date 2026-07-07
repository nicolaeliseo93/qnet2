<?php

use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('productUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function productUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("products.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("products.{$ability}");
        }

        return $user;
    }
}

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
// AC-019 — a base-authz 403 takes precedence over a field-level 422
// ---------------------------------------------------------------------------

it('a 403 (no base write ability) takes precedence over a field-level 422', function () {
    $actor = productUserWith([]);
    $product = Product::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/products/{$product->id}", ['name' => 'Nope'])->assertForbidden();
});

// ---------------------------------------------------------------------------
// AC-019 — a non-editable GENERIC field changed by the DB matrix → 422
// ---------------------------------------------------------------------------

it('update: a generic field made non-editable by the DB matrix and changed → 422', function () {
    foreach (['viewAny', 'view', 'update'] as $ability) {
        Permission::findOrCreate("products.{$ability}");
    }

    $role = Role::create(['name' => 'product-price-locked']);
    $role->givePermissionTo(['products.view', 'products.update']);
    $role->fieldPermissions()->create([
        'resource' => 'products',
        'field' => 'price',
        'visible' => true,
        'editable' => false,
        'required' => false,
    ]);

    $actor = User::factory()->create();
    $actor->assignRole($role);

    $product = Product::factory()->create(['price' => 10]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/products/{$product->id}", ['price' => 999])
        ->assertStatus(422)->assertJsonValidationErrors('price');

    expect((float) $product->fresh()->price)->toBe(10.0);
});

it('update: an untouched non-editable field is a harmless no-op (unrelated field still updates)', function () {
    foreach (['viewAny', 'view', 'update'] as $ability) {
        Permission::findOrCreate("products.{$ability}");
    }

    $role = Role::create(['name' => 'product-price-locked-2']);
    $role->givePermissionTo(['products.view', 'products.update']);
    $role->fieldPermissions()->create([
        'resource' => 'products',
        'field' => 'price',
        'visible' => true,
        'editable' => false,
        'required' => false,
    ]);

    $actor = User::factory()->create();
    $actor->assignRole($role);

    $product = Product::factory()->create(['name' => 'Before', 'price' => 10]);
    Sanctum::actingAs($actor);

    // Re-submits the SAME persisted value (the decimal cast reads back
    // "10.00") — a harmless no-op on the locked field, not a "change".
    $this->patchJson("/api/products/{$product->id}", ['name' => 'After', 'price' => '10.00'])
        ->assertOk()
        ->assertJsonPath('data.name', 'After');
});

// ---------------------------------------------------------------------------
// AC-020 — permissions:sync creates the 7 standard permissions
// ---------------------------------------------------------------------------

it('permissions:sync creates all 7 products.* permissions', function () {
    $this->artisan('permissions:sync')->assertSuccessful();

    foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
        expect(Permission::where('name', "products.{$ability}")->exists())->toBeTrue();
    }
});

// ---------------------------------------------------------------------------
// AC-020 — navigation node gated by products.view
// ---------------------------------------------------------------------------

it('navigation: the products node only shows with products.view', function () {
    Permission::findOrCreate('products.view');

    $withoutView = User::factory()->create();
    Sanctum::actingAs($withoutView);
    $group = productsNavigationGroup($this->getJson('/api/navigation')->json('data'));
    expect(collect(data_get($group, 'children', []))->pluck('key'))->not->toContain('products');

    $withView = User::factory()->create();
    $withView->givePermissionTo('products.view');
    Sanctum::actingAs($withView);
    $group = productsNavigationGroup($this->getJson('/api/navigation')->json('data'));
    expect(collect(data_get($group, 'children', []))->pluck('key'))->toContain('products');
});

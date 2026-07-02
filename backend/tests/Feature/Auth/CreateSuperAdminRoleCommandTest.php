<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('creates the super-admin role and syncs all current permissions', function () {
    Permission::create(['name' => 'users.view']);
    Permission::create(['name' => 'users.delete']);

    $this->artisan('roles:create-super-admin')->assertSuccessful();

    $role = Role::where('name', 'super-admin')->first();

    expect($role)->not->toBeNull()
        ->and($role->permissions()->pluck('name')->all())
        ->toEqualCanonicalizing(['users.view', 'users.delete']);
});

it('is idempotent and does not duplicate the super-admin role', function () {
    $this->artisan('roles:create-super-admin')->assertSuccessful();
    $this->artisan('roles:create-super-admin')->assertSuccessful();

    expect(Role::where('name', 'super-admin')->count())->toBe(1);
});

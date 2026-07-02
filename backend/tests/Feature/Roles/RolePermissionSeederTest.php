<?php

use App\Models\Role;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates the development roles with coherent permission sets', function () {
    $this->seed(RolePermissionSeeder::class);

    $admin = Role::findByName('admin');
    $manager = Role::findByName('manager');
    $operator = Role::findByName('operator');
    $user = Role::findByName('user');
    $viewer = Role::findByName('viewer');

    expect($admin->permissions)->not->toBeEmpty()
        ->and($admin->permissions->pluck('name'))->toContain('users.delete', 'roles.delete')
        ->and($manager->permissions->pluck('name'))->toContain('users.create', 'users.update', 'roles.view')
        ->and($manager->permissions->pluck('name'))->not->toContain('roles.delete', 'users.delete')
        ->and($operator->permissions->pluck('name'))->toContain('contacts.update', 'addresses.update')
        ->and($operator->permissions->pluck('name'))->not->toContain('roles.view')
        ->and($user->permissions->pluck('name'))->toContain('personal_data.view', 'contacts.view', 'addresses.view')
        ->and($user->permissions->pluck('name'))->not->toContain('users.update', 'roles.view')
        ->and($viewer->permissions->pluck('name'))->toContain('users.viewAny', 'users.view', 'roles.viewAny', 'roles.view')
        ->and($viewer->permissions->pluck('name')->every(
            fn (string $permission): bool => str_ends_with($permission, '.viewAny') || str_ends_with($permission, '.view')
        ))->toBeTrue();
});

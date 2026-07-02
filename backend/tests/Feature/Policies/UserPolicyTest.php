<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

it('denies CRUD abilities when the user lacks the permission', function () {
    $user = User::factory()->create();
    $target = User::factory()->create();

    expect($user->can('viewAny', User::class))->toBeFalse()
        ->and($user->can('update', $target))->toBeFalse()
        ->and($user->can('delete', $target))->toBeFalse();
});

it('allows an ability mapped to the matching resource permission', function () {
    Permission::create(['name' => 'users.update']);

    $user = User::factory()->create();
    $user->givePermissionTo('users.update');
    $target = User::factory()->create();

    expect($user->can('update', $target))->toBeTrue()
        ->and($user->can('delete', $target))->toBeFalse();
});

it('allows every ability to the super-admin role without explicit permissions', function () {
    Role::create(['name' => 'super-admin']);

    $user = User::factory()->create();
    $user->assignRole('super-admin');
    $target = User::factory()->create();

    expect($user->can('viewAny', User::class))->toBeTrue()
        ->and($user->can('update', $target))->toBeTrue()
        ->and($user->can('delete', $target))->toBeTrue();
});

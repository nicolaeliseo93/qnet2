<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

it('requires authentication', function () {
    $this->getJson('/api/navigation')->assertUnauthorized();
});

it('returns items without a permission requirement to any authenticated user', function () {
    config(['navigation.items' => [
        ['key' => 'dashboard', 'label' => 'navigation.dashboard', 'icon' => 'home', 'route' => '/dashboard', 'permission' => null],
    ]]);

    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/navigation')
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.key', 'dashboard')
        ->assertJsonPath('data.0.route', '/dashboard');
});

it('hides items the user lacks permission for', function () {
    config(['navigation.items' => [
        ['key' => 'users', 'label' => 'navigation.users', 'route' => '/users', 'permission' => 'users.view'],
    ]]);

    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/navigation')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

it('shows items the user has permission for', function () {
    config(['navigation.items' => [
        ['key' => 'users', 'label' => 'navigation.users', 'route' => '/users', 'permission' => 'users.view'],
    ]]);

    Permission::create(['name' => 'users.view']);
    $user = User::factory()->create();
    $user->givePermissionTo('users.view');

    Sanctum::actingAs($user);

    $this->getJson('/api/navigation')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.key', 'users');
});

it('exposes the node type, defaulting to "item"', function () {
    config(['navigation.items' => [
        ['key' => 'dashboard', 'label' => 'navigation.dashboard', 'route' => '/dashboard', 'permission' => null],
    ]]);

    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/navigation')
        ->assertOk()
        ->assertJsonPath('data.0.type', 'item');
});

it('returns a section with its children rendered flat', function () {
    config(['navigation.items' => [
        [
            'key' => 'management', 'label' => 'navigation.management', 'route' => null,
            'permission' => null, 'type' => 'section',
            'children' => [
                ['key' => 'roles', 'label' => 'navigation.roles', 'route' => '/roles', 'permission' => 'roles.view'],
            ],
        ],
    ]]);

    Permission::create(['name' => 'roles.view']);
    $user = User::factory()->create();
    $user->givePermissionTo('roles.view');

    Sanctum::actingAs($user);

    $this->getJson('/api/navigation')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.key', 'management')
        ->assertJsonPath('data.0.type', 'section')
        ->assertJsonPath('data.0.children.0.key', 'roles');
});

it('drops empty groups but keeps groups with visible children', function () {
    config(['navigation.items' => [
        [
            'key' => 'admin', 'label' => 'navigation.admin', 'route' => null, 'permission' => null,
            'children' => [
                ['key' => 'roles', 'label' => 'navigation.roles', 'route' => '/roles', 'permission' => 'roles.view'],
            ],
        ],
        [
            'key' => 'settings', 'label' => 'navigation.settings', 'route' => null, 'permission' => null,
            'children' => [
                ['key' => 'profile', 'label' => 'navigation.profile', 'route' => '/profile', 'permission' => null],
            ],
        ],
    ]]);

    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/navigation')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.key', 'settings')
        ->assertJsonPath('data.0.children.0.key', 'profile');
});

<?php

use App\Authorization\FieldPermissionRepository;
use App\Models\Role;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

// Touches the database, so bind the full TestCase + RefreshDatabase (the
// default Pest binding only applies to the Feature suite — see tests/Pest.php).
uses(TestCase::class, RefreshDatabase::class);

it('returns an empty collection for an empty role-id set (no query)', function () {
    expect((new FieldPermissionRepository)->forRoleIds([]))->toBeEmpty();
});

it('a (resource, field) with no row at all among the given roles is absent from the result', function () {
    $role = Role::create(['name' => 'empty-matrix']);

    $config = (new FieldPermissionRepository)->forRoleIds([$role->id]);

    expect($config->get('users.email'))->toBeNull();
});

it('keys the result by "resource.field"', function () {
    $role = Role::create(['name' => 'keyed-role']);
    $role->fieldPermissions()->create(['resource' => 'roles', 'field' => 'name', 'visible' => false, 'editable' => false, 'required' => false]);

    $config = (new FieldPermissionRepository)->forRoleIds([$role->id]);

    expect($config->keys()->all())->toBe(['roles.name']);
});

it('unions boolean flags across multiple roles (OR / most-permissive, RBAC-additive)', function () {
    $roleA = Role::create(['name' => 'union-a']);
    $roleA->fieldPermissions()->create(['resource' => 'users', 'field' => 'email', 'visible' => false, 'editable' => true, 'required' => false]);

    $roleB = Role::create(['name' => 'union-b']);
    $roleB->fieldPermissions()->create(['resource' => 'users', 'field' => 'email', 'visible' => true, 'editable' => false, 'required' => true]);

    $config = (new FieldPermissionRepository)->forRoleIds([$roleA->id, $roleB->id]);

    expect($config->get('users.email'))->toBe(['visible' => true, 'editable' => true, 'required' => true]);
});

it('a role NOT in the given id set does not contribute to the union', function () {
    $included = Role::create(['name' => 'included']);
    $included->fieldPermissions()->create(['resource' => 'users', 'field' => 'email', 'visible' => false, 'editable' => false, 'required' => false]);

    $excluded = Role::create(['name' => 'excluded']);
    $excluded->fieldPermissions()->create(['resource' => 'users', 'field' => 'email', 'visible' => true, 'editable' => true, 'required' => true]);

    $config = (new FieldPermissionRepository)->forRoleIds([$included->id]);

    expect($config->get('users.email'))->toBe(['visible' => false, 'editable' => false, 'required' => false]);
});

it('memoizes: issues exactly one role_field_permissions query per distinct role-id set across repeated calls', function () {
    $role = Role::create(['name' => 'memoized']);
    $role->fieldPermissions()->create(['resource' => 'users', 'field' => 'email', 'visible' => false, 'editable' => true, 'required' => false]);

    $repository = new FieldPermissionRepository;

    DB::enableQueryLog();
    $repository->forRoleIds([$role->id]);
    $repository->forRoleIds([$role->id]);
    $repository->forRoleIds([$role->id]);
    $matrixQueries = collect(DB::getQueryLog())
        ->filter(fn (array $query): bool => str_contains($query['query'], 'role_field_permissions'))
        ->count();
    DB::disableQueryLog();

    expect($matrixQueries)->toBe(1);
});

it('normalizes the role-id set (dedupe + order-independent) so equivalent sets share the cache', function () {
    $roleA = Role::create(['name' => 'norm-a']);
    $roleB = Role::create(['name' => 'norm-b']);
    $roleA->fieldPermissions()->create(['resource' => 'users', 'field' => 'email', 'visible' => false, 'editable' => false, 'required' => false]);

    $repository = new FieldPermissionRepository;

    DB::enableQueryLog();
    $first = $repository->forRoleIds([$roleA->id, $roleB->id]);
    $second = $repository->forRoleIds([$roleB->id, $roleA->id, $roleA->id]); // reordered + duplicated
    $matrixQueries = collect(DB::getQueryLog())
        ->filter(fn (array $query): bool => str_contains($query['query'], 'role_field_permissions'))
        ->count();
    DB::disableQueryLog();

    expect($matrixQueries)->toBe(1)
        ->and($first->get('users.email'))->toBe($second->get('users.email'));
});

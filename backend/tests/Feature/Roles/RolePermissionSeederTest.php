<?php

use App\Models\Role;
use App\Services\RoleAssignmentGuard;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

it('bootstraps the permission catalogue and only the privileged super-admin role', function () {
    $this->seed(RolePermissionSeeder::class);

    expect(Permission::query()->exists())->toBeTrue()
        ->and(Role::query()->pluck('name')->all())->toBe([RoleAssignmentGuard::PRIVILEGED_ROLE]);
});

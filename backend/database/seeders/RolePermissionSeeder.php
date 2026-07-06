<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\PermissionRegistrar;

/**
 * Bootstrap the permission catalogue (reference data) and the single privileged
 * `super-admin` role. This is all the clean default seed needs; the extra
 * non-privileged application roles used by fake fixtures live in DemoRolesSeeder.
 */
class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        Artisan::call('permissions:sync');
        Artisan::call('roles:create-super-admin');

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}

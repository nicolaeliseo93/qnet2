<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Guard;

class RolePermissionSeeder extends Seeder
{
    /**
     * Seed the role catalogue used by development fixtures.
     */
    public function run(): void
    {
        Artisan::call('permissions:sync');
        Artisan::call('roles:create-super-admin');

        $allPermissions = Permission::query()->pluck('name');

        $this->syncRole('admin', $allPermissions);
        $this->syncRole('manager', $this->managerPermissions($allPermissions));
        $this->syncRole('operator', $this->operatorPermissions($allPermissions));
        $this->syncRole('user', $this->userPermissions($allPermissions));
        $this->syncRole('viewer', $this->viewerPermissions($allPermissions));

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    private function syncRole(string $name, Collection $permissions): void
    {
        $role = Role::findOrCreate($name, Guard::getDefaultName(User::class));
        $role->syncPermissions($permissions->all());
    }

    private function managerPermissions(Collection $allPermissions): Collection
    {
        return $this->resourceAbilities(
            $allPermissions,
            [
                'users' => ['viewAny', 'view', 'create', 'update'],
                'roles' => ['viewAny', 'view'],
                'personal_data' => ['viewAny', 'view'],
                'contacts' => ['viewAny', 'view'],
                'addresses' => ['viewAny', 'view'],
                'attachments' => ['viewAny', 'view'],
            ],
        );
    }

    private function operatorPermissions(Collection $allPermissions): Collection
    {
        return $this->resourceAbilities(
            $allPermissions,
            [
                'users' => ['viewAny', 'view'],
                'personal_data' => ['viewAny', 'view', 'create', 'update'],
                'contacts' => ['viewAny', 'view', 'create', 'update'],
                'addresses' => ['viewAny', 'view', 'create', 'update'],
                'attachments' => ['viewAny', 'view', 'create', 'update'],
            ],
        );
    }

    private function userPermissions(Collection $allPermissions): Collection
    {
        return $this->resourceAbilities(
            $allPermissions,
            [
                'personal_data' => ['viewAny', 'view'],
                'contacts' => ['viewAny', 'view'],
                'addresses' => ['viewAny', 'view'],
                'attachments' => ['viewAny', 'view'],
            ],
        );
    }

    private function viewerPermissions(Collection $allPermissions): Collection
    {
        return $allPermissions->filter(
            static fn (string $permission): bool => str_ends_with($permission, '.viewAny')
                || str_ends_with($permission, '.view')
        )->values();
    }

    /**
     * @param  array<string, array<int, string>>  $matrix
     */
    private function resourceAbilities(Collection $allPermissions, array $matrix): Collection
    {
        $wanted = collect($matrix)
            ->flatMap(static fn (array $abilities, string $resource): array => array_map(
                static fn (string $ability): string => "{$resource}.{$ability}",
                $abilities,
            ));

        return $allPermissions->intersect($wanted)->values();
    }
}

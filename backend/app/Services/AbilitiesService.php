<?php

namespace App\Services;

use App\Models\User;
use Spatie\Permission\Models\Permission;

class AbilitiesService
{
    /**
     * Full ability map for the user: every defined permission with a boolean,
     * plus the user's role names.
     *
     * @return array{roles: array<int, string>, permissions: array<string, bool>}
     */
    public function for(User $user): array
    {
        if ($user->hasRole('super-admin')) {
            $permissions = Permission::orderBy('name')
                ->pluck('name')
                ->mapWithKeys(fn (string $name) => [$name => true])
                ->all();

            return [
                'roles' => $user->getRoleNames()->all(),
                'permissions' => $permissions,
            ];
        }

        $granted = $user->getAllPermissions()->pluck('name')->flip();

        $permissions = Permission::orderBy('name')
            ->pluck('name')
            ->mapWithKeys(fn (string $name) => [$name => $granted->has($name)])
            ->all();

        return [
            'roles' => $user->getRoleNames()->all(),
            'permissions' => $permissions,
        ];
    }
}

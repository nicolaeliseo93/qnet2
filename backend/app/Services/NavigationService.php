<?php

namespace App\Services;

use App\Models\User;

class NavigationService
{
    /**
     * Build the navigation tree visible to the given user.
     *
     * @return array<int, array<string, mixed>>
     */
    public function for(User $user): array
    {
        return $this->filter(config('navigation.items', []), $user);
    }

    /**
     * All distinct permissions referenced by the navigation config.
     *
     * @return array<int, string>
     */
    public function permissions(): array
    {
        return array_values(array_unique(
            $this->collectPermissions(config('navigation.items', []))
        ));
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, string>
     */
    private function collectPermissions(array $items): array
    {
        $permissions = [];

        foreach ($items as $item) {
            if (! empty($item['permission'])) {
                $permissions[] = $item['permission'];
            }

            if (! empty($item['children'])) {
                $permissions = array_merge(
                    $permissions,
                    $this->collectPermissions($item['children'])
                );
            }
        }

        return $permissions;
    }

    /**
     * Recursively keep only the items the user is allowed to see.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function filter(array $items, User $user): array
    {
        $visible = [];

        foreach ($items as $item) {
            if (! $this->isAllowed($item, $user)) {
                continue;
            }

            $children = $this->filter($item['children'] ?? [], $user);

            // A group (no own route) whose children are all hidden is dropped.
            if (empty($item['route'] ?? null) && ! empty($item['children'] ?? null) && empty($children)) {
                continue;
            }

            $item['children'] = $children;
            $visible[] = $item;
        }

        return $visible;
    }

    /**
     * An item with no permission is public to authenticated users. An
     * additional, optional `role` (spec 0013) gates the item to users holding
     * that Spatie role — combined with the permission check (both must pass).
     */
    private function isAllowed(array $item, User $user): bool
    {
        $permission = $item['permission'] ?? null;
        $role = $item['role'] ?? null;

        $permissionAllowed = $permission === null || $user->can($permission);
        $roleAllowed = $role === null || $user->hasRole($role);

        return $permissionAllowed && $roleAllowed;
    }
}

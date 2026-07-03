<?php

namespace App\Imports;

use App\DataObjects\Roles\CreateRoleData;
use App\Models\Role;
use App\Models\User;
use App\Services\RoleService;
use Spatie\Permission\Models\Permission;

/**
 * Import definition for `roles`.
 *
 * Columns: `name` (required, natural key) + `permissions` (optional). The
 * `permissions` cell holds a PIPE-delimited (`|`, not `,` — the CSV field
 * separator is already a comma) list of EXISTING permission names, e.g.
 * `companies.view|companies.create`. Each split name must exist in the
 * `permissions` table — mirrors StoreRoleRequest's own
 * `Rule::exists('permissions', 'name')` (not guard-scoped, not restricted to
 * any "assignable" catalogue: matches the frozen role-create contract as-is).
 *
 * Row creation delegates entirely to RoleService::create() (no duplicated
 * logic): `users` (role membership) is never set by import (not a column
 * here), matching a plain CSV row with no picker UI.
 */
class RolesImportDefinition extends AbstractImportDefinition
{
    public function __construct(private readonly RoleService $service) {}

    public function domain(): string
    {
        return 'roles';
    }

    public function modelClass(): string
    {
        return Role::class;
    }

    public function columns(): array
    {
        return [
            ['id' => 'name', 'required' => true],
            ['id' => 'permissions', 'required' => false],
        ];
    }

    public function validateRow(array $row, ImportRowContext $context): array
    {
        $errors = [];

        if (trim($row['name'] ?? '') === '') {
            $errors[] = 'name is required.';
        }

        $unknown = $this->unknownPermissionNames($row);

        if ($unknown !== []) {
            $errors[] = 'Unknown permission(s): '.implode(', ', $unknown).'.';
        }

        return $errors;
    }

    public function dedupKey(array $row): ?string
    {
        $name = trim($row['name'] ?? '');

        return $name === '' ? null : mb_strtolower($name);
    }

    /**
     * Fetches only the `name` column and compares in PHP (no raw SQL), same
     * trade-off as GeoResolver/the other definitions.
     */
    public function existsInDatabase(string $key): bool
    {
        return Role::query()
            ->get(['name'])
            ->contains(static fn (Role $role): bool => mb_strtolower($role->name) === $key);
    }

    public function createRow(User $actor, array $row): void
    {
        $names = $this->splitPipeList($row['permissions'] ?? null);

        $this->service->create($actor, new CreateRoleData(
            name: $row['name'],
            permissions: $names !== [] ? $names : null,
        ));
    }

    /**
     * Permission names present in the cell but NOT found in the `permissions`
     * table.
     *
     * @param  array<string, string>  $row
     * @return array<int, string>
     */
    private function unknownPermissionNames(array $row): array
    {
        $names = $this->splitPipeList($row['permissions'] ?? null);

        if ($names === []) {
            return [];
        }

        $existing = Permission::query()->whereIn('name', $names)->pluck('name')->all();

        return array_values(array_diff($names, $existing));
    }
}

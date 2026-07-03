<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Models\RoleFieldPermission;
use Illuminate\Support\Collection;

/**
 * Per-request memoized reader for `role_field_permissions` (spec 0006).
 *
 * `forRoleIds()` issues exactly ONE query per distinct role-id set (memoized
 * in-process — bind this class as a container singleton, see
 * AppServiceProvider, so the memoization spans the whole request) and
 * returns the ALREADY-UNIONED (additive, most-permissive RBAC semantics —
 * "if ANY of the actor's roles grants a flag, it is granted") config for
 * every `(resource, field)` that has at least one row among the given roles,
 * keyed by `"{resource}.{field}"`.
 *
 * A `(resource, field)` pair ABSENT from the returned collection has no row
 * at all among the given roles — the caller
 * (AbstractResourceAuthorization::fieldPermissions) treats that as
 * full/unrestricted, per the spec 0006 merge semantics.
 */
class FieldPermissionRepository
{
    /**
     * @var array<string, Collection<string, array{visible: bool, editable: bool, required: bool}>>
     */
    private array $cache = [];

    /**
     * @param  array<int, int>  $roleIds
     * @return Collection<string, array{visible: bool, editable: bool, required: bool}>
     */
    public function forRoleIds(array $roleIds): Collection
    {
        $roleIds = $this->normalize($roleIds);

        if ($roleIds === []) {
            return collect();
        }

        $cacheKey = implode(',', $roleIds);

        return $this->cache[$cacheKey] ??= $this->query($roleIds);
    }

    /**
     * @param  array<int, int>  $roleIds
     * @return array<int, int>
     */
    private function normalize(array $roleIds): array
    {
        $unique = array_values(array_unique(array_map('intval', $roleIds)));
        sort($unique);

        return $unique;
    }

    /**
     * One query for the whole role set; the union across roles is then
     * computed in-memory (no per-role follow-up query).
     *
     * @param  array<int, int>  $roleIds
     * @return Collection<string, array{visible: bool, editable: bool, required: bool}>
     */
    private function query(array $roleIds): Collection
    {
        return RoleFieldPermission::query()
            ->whereIn('role_id', $roleIds)
            ->get(['resource', 'field', 'visible', 'editable', 'required'])
            ->groupBy(static fn (RoleFieldPermission $row): string => "{$row->resource}.{$row->field}")
            ->map(static fn (Collection $rows): array => [
                'visible' => $rows->contains(static fn (RoleFieldPermission $row): bool => $row->visible),
                'editable' => $rows->contains(static fn (RoleFieldPermission $row): bool => $row->editable),
                'required' => $rows->contains(static fn (RoleFieldPermission $row): bool => $row->required),
            ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Models\User;
use App\Services\RoleAssignmentGuard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Shared defaults for every concrete `ResourceAuthorization`. A concrete
 * class only declares its field/action catalogues and its
 * `fieldPermissionCeiling()` (the security ceiling), and overrides
 * `resourcePermissions()`/`actionPermissions()` where the rule differs from
 * the default (see UsersAuthorization/RolesAuthorization for the reference
 * shape).
 *
 * `fieldPermissions()` (spec 0006) is FINAL here: it merges the concrete
 * class' `fieldPermissionCeiling()` with the actor's DB-configured
 * `role_field_permissions` matrix by INTERSECTION — the DB can only restrict
 * within the ceiling, never escalate. This is the single choke point for
 * that invariant; no subclass can bypass it by overriding fieldPermissions().
 */
abstract class AbstractResourceAuthorization implements ResourceAuthorization
{
    /**
     * Fixed set of standard resource abilities, each mapped to the Spatie
     * permission "{resource}.{ability}" via the BasePolicy convention.
     */
    private const array RESOURCE_ABILITIES = ['view', 'create', 'update', 'delete', 'export', 'import'];

    public function __construct(protected readonly FieldPermissionRepository $fieldPermissionRepository) {}

    /**
     * Default: every ability → `$actor->can("{resource}.{ability}")`.
     *
     * @return array<string, bool>
     */
    public function resourcePermissions(User $actor, ?Model $model): array
    {
        $permissions = [];

        foreach (self::RESOURCE_ABILITIES as $ability) {
            $permissions[$ability] = $actor->can("{$this->resource()}.{$ability}");
        }

        return $permissions;
    }

    /**
     * FINAL (spec 0006): `final = intersect(fieldPermissionCeiling(), dbConfig)`,
     * with a privileged-role bypass. A super-admin actor (`Gate::before`)
     * always gets the full, unrestricted ceiling — the DB matrix is
     * meaningless to them, consistent with how every other ability already
     * bypasses Policy checks for them.
     *
     * For every other actor, each ceiling field is intersected with that
     * actor's DB-configured matrix (union across their roles — see
     * FieldPermissionRepository): a `(resource, field)` with NO db row at all
     * is full/unrestricted (today's 0004 behavior, unchanged); a `(resource,
     * field)` WITH a db row can only narrow the ceiling, never widen it.
     *
     * @return array<string, FieldPermission>
     */
    final public function fieldPermissions(User $actor, ?Model $model): array
    {
        $ceiling = $this->fieldPermissionCeiling($actor, $model);

        if ($actor->hasRole(RoleAssignmentGuard::PRIVILEGED_ROLE)) {
            return $ceiling;
        }

        $dbConfig = $this->dbFieldConfig($actor);
        $mandatory = $this->mandatoryFieldKeys();

        $merged = [];

        foreach ($ceiling as $field => $ceilingPermission) {
            // Mandatory fields (spec 0008) are vital to creating the resource:
            // the DB matrix may never narrow them, so they bypass the intersect
            // and keep the full ceiling — the server-side twin of the locked,
            // disabled checkboxes the Role matrix shows for them.
            $merged[$field] = isset($mandatory[$field])
                ? $ceilingPermission
                : $this->mergeFieldPermission($ceilingPermission, $dbConfig->get("{$this->resource()}.{$field}"));
        }

        return $merged;
    }

    /**
     * The `fields()` keys flagged `mandatory` (spec 0008), as a lookup set.
     *
     * @return array<string, true>
     */
    private function mandatoryFieldKeys(): array
    {
        $keys = [];

        foreach ($this->fields() as $definition) {
            if ($definition->mandatory) {
                $keys[$definition->key] = true;
            }
        }

        return $keys;
    }

    /**
     * The security CEILING (spec 0004 behavior, renamed): every field
     * visible+editable when the actor may write (create when $model is null,
     * else update), else visible+readonly, by default. Concrete classes
     * override per field for their contextual rules — this is the ONLY
     * field-permission method a concrete class implements; `fieldPermissions()`
     * itself is final (see class docblock).
     *
     * @return array<string, FieldPermission>
     */
    abstract protected function fieldPermissionCeiling(User $actor, ?Model $model): array;

    /**
     * Intersect one ceiling field with its DB config (spec 0006 merge
     * semantics). `$db === null` means no role_field_permissions row exists
     * for this field among the actor's roles — full/unrestricted, the ceiling
     * passes through unchanged.
     *
     * `disabled` has no DB column (the matrix only expresses
     * visible/editable/required): a ceiling-disabled field can never be
     * un-disabled by DB config, and `editable` is already false whenever
     * `disabled` is true (by construction of FieldPermission::disabled()), so
     * the intersect already keeps it non-editable — this only preserves the
     * `disabled` (vs plain `readonly`) distinction in the merged output.
     *
     * @param  array{visible: bool, editable: bool, required: bool}|null  $db
     */
    private function mergeFieldPermission(FieldPermission $ceiling, ?array $db): FieldPermission
    {
        if ($db === null) {
            return $ceiling;
        }

        $visible = $ceiling->visible && $db['visible'];
        $editable = $ceiling->editable && $db['editable'];
        $required = $ceiling->required || ($db['required'] && $editable);

        return match (true) {
            ! $visible => FieldPermission::hidden(),
            $ceiling->disabled => FieldPermission::disabled(),
            ! $editable => FieldPermission::visibleReadonly(),
            default => FieldPermission::visibleEditable(required: $required),
        };
    }

    /**
     * The actor's DB-configured field-permission matrix, unioned (most
     * permissive) across every role they hold (RBAC-additive), keyed by
     * `"{resource}.{field}"`. One repository query for the whole role set
     * (memoized per request — see FieldPermissionRepository).
     *
     * @return Collection<string, array{visible: bool, editable: bool, required: bool}>
     */
    private function dbFieldConfig(User $actor): Collection
    {
        /** @var array<int, int> $roleIds */
        $roleIds = $actor->roles->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all();

        return $this->fieldPermissionRepository->forRoleIds($roleIds);
    }

    /**
     * Default: every action gated by the permission of the same name on this
     * resource ("{resource}.{action}"). Concrete classes override for actions
     * mapped to a different permission or a contextual rule.
     *
     * @return array<string, bool>
     */
    public function actionPermissions(User $actor, ?Model $model): array
    {
        $permissions = [];

        foreach ($this->actions() as $action) {
            $permissions[$action] = $actor->can("{$this->resource()}.{$action}");
        }

        return $permissions;
    }

    /**
     * Whether $actor may write this resource in the current context: create
     * ability when there is no instance yet ($model === null), else update.
     */
    protected function actorMayWrite(User $actor, ?Model $model): bool
    {
        $ability = $model === null ? 'create' : 'update';

        return $actor->can("{$this->resource()}.{$ability}");
    }

    /**
     * Contextual hook: whether a resource-state rule (e.g. paid/cancelled)
     * applies to $model. Present but no-op here — no module wired to this
     * slice needs it yet; override in a future resource-state-aware resource.
     */
    protected function appliesResourceState(User $actor, ?Model $model): bool
    {
        return true;
    }

    /**
     * Contextual hook: whether an ownership rule applies to $model. Present
     * but no-op here; override in a future ownership-scoped resource.
     */
    protected function appliesOwnership(User $actor, ?Model $model): bool
    {
        return true;
    }

    /**
     * Contextual hook: whether a location/site-scope rule applies to $model.
     * Present but no-op here; override in a future site-scoped resource.
     */
    protected function appliesLocation(User $actor, ?Model $model): bool
    {
        return true;
    }
}

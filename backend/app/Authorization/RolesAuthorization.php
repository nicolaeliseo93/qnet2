<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Models\Role;
use App\Models\User;
use App\Services\RoleAssignmentGuard;
use Illuminate\Database\Eloquent\Model;

/**
 * ResourceAuthorization for the `roles` resource (spec 0004).
 *
 * Reuses RoleAssignmentGuard::PRIVILEGED_ROLE (single source of truth for the
 * super-admin role name) — no super-admin logic is duplicated here. The
 * membership escalation filter itself stays owned by RoleAssignmentGuard /
 * RoleService; this class only decides which fields are editable at all.
 */
class RolesAuthorization extends AbstractResourceAuthorization
{
    public function __construct(FieldPermissionRepository $fieldPermissionRepository)
    {
        parent::__construct($fieldPermissionRepository);
    }

    public function resource(): string
    {
        return 'roles';
    }

    /**
     * @return array<int, FieldDefinition>
     */
    public function fields(): array
    {
        return [
            new FieldDefinition('name', 'text'),
            new FieldDefinition('permissions', 'multiselect'),
            new FieldDefinition('users', 'multiselect'),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function actions(): array
    {
        return ['delete', 'export', 'import'];
    }

    /**
     * The security ceiling for `roles` fields (spec 0004 rules, unchanged;
     * spec 0006 renamed this from `fieldPermissions()` — the DB matrix merge
     * now lives once in AbstractResourceAuthorization::fieldPermissions()).
     *
     * @return array<string, FieldPermission>
     */
    protected function fieldPermissionCeiling(User $actor, ?Model $model): array
    {
        /** @var Role|null $model */
        $isSystemRole = $model !== null && $model->name === RoleAssignmentGuard::PRIVILEGED_ROLE;

        if ($isSystemRole) {
            return $this->systemRoleFieldPermissions($actor);
        }

        $mayWrite = $this->actorMayWrite($actor, $model);

        return [
            'name' => $mayWrite ? FieldPermission::visibleEditable(required: true) : FieldPermission::visibleReadonly(),
            'permissions' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'users' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
        ];
    }

    /**
     * The protected `super-admin` system role (mirrors RoleService::guardSystemRole):
     * every field is locked for a non-super-admin actor; a super-admin actor
     * may still change name/permissions/members here — RoleService's own
     * guardSystemRoleMutation remains the final, unconditional authority that
     * blocks name/permission mutation even for a super-admin actor.
     *
     * @return array<string, FieldPermission>
     */
    private function systemRoleFieldPermissions(User $actor): array
    {
        $isSuperAdminActor = $actor->hasRole(RoleAssignmentGuard::PRIVILEGED_ROLE);

        $permission = $isSuperAdminActor ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly();

        return [
            'name' => $isSuperAdminActor ? FieldPermission::visibleEditable(required: true) : FieldPermission::visibleReadonly(),
            'permissions' => $permission,
            'users' => $permission,
        ];
    }

    /**
     * @return array<string, bool>
     */
    public function actionPermissions(User $actor, ?Model $model): array
    {
        /** @var Role|null $model */
        return [
            // Mirrors RolesTableDefinition::actionsFor: never on the protected
            // system role, regardless of the actor.
            'delete' => $model !== null
                && $model->name !== RoleAssignmentGuard::PRIVILEGED_ROLE
                && $actor->can('roles.delete'),
            'export' => $actor->can('roles.export'),
            'import' => $actor->can('roles.import'),
        ];
    }
}

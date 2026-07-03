<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Models\User;
use App\Services\RoleAssignmentGuard;
use Illuminate\Database\Eloquent\Model;

/**
 * ResourceAuthorization for the `users` resource (spec 0004).
 *
 * Reuses RoleAssignmentGuard::PRIVILEGED_ROLE (single source of truth for the
 * super-admin role name) — no super-admin logic is duplicated here. The
 * per-role-id escalation filter (which roles an actor may actually assign)
 * stays owned by RoleAssignmentGuard / ResolvesAssignableRoles; this class
 * only decides whether the whole `roles` field is editable at all.
 */
class UsersAuthorization extends AbstractResourceAuthorization
{
    public function __construct(FieldPermissionRepository $fieldPermissionRepository)
    {
        parent::__construct($fieldPermissionRepository);
    }

    public function resource(): string
    {
        return 'users';
    }

    /**
     * @return array<int, FieldDefinition>
     */
    public function fields(): array
    {
        return [
            new FieldDefinition('email', 'email'),
            new FieldDefinition('locale', 'select'),
            new FieldDefinition('roles', 'multiselect'),
            new FieldDefinition('password', 'password'),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function actions(): array
    {
        return ['delete', 'export', 'import', 'upload_avatar', 'delete_avatar'];
    }

    /**
     * The security ceiling for `users` fields (spec 0004 rules, unchanged;
     * spec 0006 renamed this from `fieldPermissions()` — the DB matrix merge
     * now lives once in AbstractResourceAuthorization::fieldPermissions()).
     *
     * @return array<string, FieldPermission>
     */
    protected function fieldPermissionCeiling(User $actor, ?Model $model): array
    {
        /** @var User|null $model */
        $mayWrite = $this->actorMayWrite($actor, $model);
        $isCreate = $model === null;

        return [
            'email' => $mayWrite ? FieldPermission::visibleEditable(required: true) : FieldPermission::visibleReadonly(),
            'locale' => $mayWrite ? FieldPermission::visibleEditable(required: true) : FieldPermission::visibleReadonly(),
            'password' => $mayWrite ? FieldPermission::visibleEditable(required: $isCreate) : FieldPermission::visibleReadonly(),
            'roles' => $this->rolesFieldPermission($actor, $model, $mayWrite),
        ];
    }

    /**
     * `roles` is editable when the actor may write, UNLESS the target is a
     * super-admin and the actor is not — mirrors the escalation boundary
     * already enforced by RoleAssignmentGuard on the write path.
     */
    private function rolesFieldPermission(User $actor, ?User $model, bool $mayWrite): FieldPermission
    {
        $targetIsPrivileged = $model !== null && $model->hasRole(RoleAssignmentGuard::PRIVILEGED_ROLE);
        $actorIsPrivileged = $actor->hasRole(RoleAssignmentGuard::PRIVILEGED_ROLE);

        if ($targetIsPrivileged && ! $actorIsPrivileged) {
            return FieldPermission::visibleReadonly();
        }

        return $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly();
    }

    /**
     * @return array<string, bool>
     */
    public function actionPermissions(User $actor, ?Model $model): array
    {
        /** @var User|null $model */
        return [
            // Mirrors UserPolicy::delete: users.delete AND never self.
            'delete' => $model !== null && ! $actor->is($model) && $actor->can('users.delete'),
            'export' => $actor->can('users.export'),
            'import' => $actor->can('users.import'),
            'upload_avatar' => $model !== null && $actor->can('users.update'),
            'delete_avatar' => $model !== null && $actor->can('users.update'),
        ];
    }
}

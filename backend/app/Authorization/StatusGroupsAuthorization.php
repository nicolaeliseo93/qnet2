<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * ResourceAuthorization for the `status-groups` resource (spec 0039).
 *
 * No contextual rules: every field's ceiling is simply visible+editable when
 * the actor may write (create/update), else visible+readonly, mirroring
 * LeadStatusesAuthorization.
 */
class StatusGroupsAuthorization extends AbstractResourceAuthorization
{
    public function __construct(FieldPermissionRepository $fieldPermissionRepository)
    {
        parent::__construct($fieldPermissionRepository);
    }

    public function resource(): string
    {
        return 'status-groups';
    }

    /**
     * @return array<int, FieldDefinition>
     */
    public function fields(): array
    {
        return [
            new FieldDefinition('name', 'text', mandatory: true),
            new FieldDefinition('color', 'color'),
            new FieldDefinition('sort_order', 'number'),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function actions(): array
    {
        return ['delete', 'export', 'import', 'view_activity'];
    }

    /**
     * @return array<string, FieldPermission>
     */
    protected function fieldPermissionCeiling(User $actor, ?Model $model): array
    {
        $mayWrite = $this->actorMayWrite($actor, $model);

        return [
            'name' => $mayWrite ? FieldPermission::visibleEditable(required: true) : FieldPermission::visibleReadonly(),
            'color' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'sort_order' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
        ];
    }

    /**
     * @return array<string, bool>
     */
    public function actionPermissions(User $actor, ?Model $model): array
    {
        return [
            'delete' => $model !== null && $actor->can('status-groups.delete'),
            'export' => $actor->can('status-groups.export'),
            'import' => $actor->can('status-groups.import'),
            // Gates the ActivityLogSection in the detail (spec 0034); the
            // record-level `status-groups.view` boundary is enforced
            // separately by GET /api/activity-log/status-groups/{id}.
            'view_activity' => $model !== null && $actor->can('status-groups.viewActivity'),
        ];
    }
}

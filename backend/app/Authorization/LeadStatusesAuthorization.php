<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * ResourceAuthorization for the `lead-statuses` resource (spec 0029).
 *
 * No contextual rules: every field's ceiling is simply visible+editable when
 * the actor may write (create/update), else visible+readonly, mirroring
 * PipelineStatusesAuthorization.
 */
class LeadStatusesAuthorization extends AbstractResourceAuthorization
{
    public function __construct(FieldPermissionRepository $fieldPermissionRepository)
    {
        parent::__construct($fieldPermissionRepository);
    }

    public function resource(): string
    {
        return 'lead-statuses';
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
            'delete' => $model !== null && $actor->can('lead-statuses.delete'),
            'export' => $actor->can('lead-statuses.export'),
            'import' => $actor->can('lead-statuses.import'),
            // Gates the ActivityLogSection in the detail (spec 0034); the
            // record-level `lead-statuses.view` boundary is enforced
            // separately by GET /api/activity-log/lead-statuses/{id}.
            'view_activity' => $model !== null && $actor->can('lead-statuses.viewActivity'),
        ];
    }
}

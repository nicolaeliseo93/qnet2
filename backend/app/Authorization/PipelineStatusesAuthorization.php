<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * ResourceAuthorization for the `pipeline-statuses` resource (spec 0023).
 *
 * No contextual rules: every field's ceiling is simply visible+editable when
 * the actor may write (create/update), else visible+readonly, mirroring
 * SourcesAuthorization.
 *
 * spec 0039, D-5: `sort_order` is REMOVED from fields() — server-managed, no
 * longer writable via the API (the table column itself is unaffected, see
 * PipelineStatusColumnCatalog). `group` (pivot, App\Enums\StatusGroup) is
 * the new field.
 */
class PipelineStatusesAuthorization extends AbstractResourceAuthorization
{
    public function __construct(FieldPermissionRepository $fieldPermissionRepository)
    {
        parent::__construct($fieldPermissionRepository);
    }

    public function resource(): string
    {
        return 'pipeline-statuses';
    }

    /**
     * @return array<int, FieldDefinition>
     */
    public function fields(): array
    {
        return [
            new FieldDefinition('name', 'text', mandatory: true),
            new FieldDefinition('color', 'color'),
            new FieldDefinition('group', 'select'),
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
            'group' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
        ];
    }

    /**
     * @return array<string, bool>
     */
    public function actionPermissions(User $actor, ?Model $model): array
    {
        return [
            'delete' => $model !== null && $actor->can('pipeline-statuses.delete'),
            'export' => $actor->can('pipeline-statuses.export'),
            'import' => $actor->can('pipeline-statuses.import'),
            // Gates the ActivityLogSection in the detail (spec 0034); the
            // record-level `pipeline-statuses.view` boundary is enforced
            // separately by GET /api/activity-log/pipeline-statuses/{id}.
            'view_activity' => $model !== null && $actor->can('pipeline-statuses.viewActivity'),
        ];
    }
}

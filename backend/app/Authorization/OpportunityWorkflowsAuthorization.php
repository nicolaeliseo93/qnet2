<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * ResourceAuthorization for the `opportunity-workflows` resource (spec 0047).
 *
 * No contextual rules: every field's ceiling is simply visible+editable when
 * the actor may write (create/update), else visible+readonly, mirroring
 * OpportunityStatusesAuthorization. `criteria`/`statuses` (the nested
 * child collections) are edited via their own request payload keys, not
 * plain scalar fields here — this catalogue only covers the workflow's own
 * columns.
 */
class OpportunityWorkflowsAuthorization extends AbstractResourceAuthorization
{
    public function __construct(FieldPermissionRepository $fieldPermissionRepository)
    {
        parent::__construct($fieldPermissionRepository);
    }

    public function resource(): string
    {
        return 'opportunity-workflows';
    }

    /**
     * @return array<int, FieldDefinition>
     */
    public function fields(): array
    {
        return [
            new FieldDefinition('name', 'text', mandatory: true),
            new FieldDefinition('is_active', 'boolean'),
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
            'is_active' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
        ];
    }

    /**
     * @return array<string, bool>
     */
    public function actionPermissions(User $actor, ?Model $model): array
    {
        return [
            'delete' => $model !== null && $actor->can('opportunity-workflows.delete'),
            'export' => $actor->can('opportunity-workflows.export'),
            'import' => $actor->can('opportunity-workflows.import'),
            // Gates the ActivityLogSection in the detail (spec 0034); the
            // record-level `opportunity-workflows.view` boundary is enforced
            // separately by GET /api/activity-log/opportunity-workflows/{id}.
            'view_activity' => $model !== null && $actor->can('opportunity-workflows.viewActivity'),
        ];
    }
}

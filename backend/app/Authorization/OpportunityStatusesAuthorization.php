<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * ResourceAuthorization for the `opportunity-statuses` resource (spec 0043).
 *
 * No contextual rules: every field's ceiling is simply visible+editable when
 * the actor may write (create/update), else visible+readonly, mirroring
 * LeadStatusesAuthorization. `sort_order` is server-managed, no longer
 * writable via the API (the table column itself is unaffected, see
 * OpportunityStatusColumnCatalog). `group` (App\Enums\StatusGroup) is the
 * fixed 3-value classification.
 */
class OpportunityStatusesAuthorization extends AbstractResourceAuthorization
{
    public function __construct(FieldPermissionRepository $fieldPermissionRepository)
    {
        parent::__construct($fieldPermissionRepository);
    }

    public function resource(): string
    {
        return 'opportunity-statuses';
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
            'delete' => $model !== null && $actor->can('opportunity-statuses.delete'),
            'export' => $actor->can('opportunity-statuses.export'),
            'import' => $actor->can('opportunity-statuses.import'),
            // Gates the ActivityLogSection in the detail (spec 0034); the
            // record-level `opportunity-statuses.view` boundary is enforced
            // separately by GET /api/activity-log/opportunity-statuses/{id}.
            'view_activity' => $model !== null && $actor->can('opportunity-statuses.viewActivity'),
        ];
    }
}

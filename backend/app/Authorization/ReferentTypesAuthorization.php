<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * ResourceAuthorization for the `referent-types` resource (spec 0016).
 *
 * No contextual rules: every field's ceiling is simply visible+editable when
 * the actor may write (create/update), else visible+readonly, mirroring
 * BusinessFunctionsAuthorization.
 */
class ReferentTypesAuthorization extends AbstractResourceAuthorization
{
    public function __construct(FieldPermissionRepository $fieldPermissionRepository)
    {
        parent::__construct($fieldPermissionRepository);
    }

    public function resource(): string
    {
        return 'referent-types';
    }

    /**
     * @return array<int, FieldDefinition>
     */
    public function fields(): array
    {
        return [
            new FieldDefinition('name', 'text', mandatory: true),
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
        ];
    }

    /**
     * @return array<string, bool>
     */
    public function actionPermissions(User $actor, ?Model $model): array
    {
        return [
            'delete' => $model !== null && $actor->can('referent-types.delete'),
            'export' => $actor->can('referent-types.export'),
            'import' => $actor->can('referent-types.import'),
            // Gates the ActivityLogSection in the detail (spec 0034); the
            // record-level `referent-types.view` boundary is enforced
            // separately by GET /api/activity-log/referent-types/{id}.
            'view_activity' => $model !== null && $actor->can('referent-types.viewActivity'),
        ];
    }
}

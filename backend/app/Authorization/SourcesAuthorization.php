<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * ResourceAuthorization for the `sources` resource (spec 0018).
 *
 * No contextual rules: every field's ceiling is simply visible+editable when
 * the actor may write (create/update), else visible+readonly, mirroring
 * ReferentTypesAuthorization.
 */
class SourcesAuthorization extends AbstractResourceAuthorization
{
    public function __construct(FieldPermissionRepository $fieldPermissionRepository)
    {
        parent::__construct($fieldPermissionRepository);
    }

    public function resource(): string
    {
        return 'sources';
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
            'delete' => $model !== null && $actor->can('sources.delete'),
            'export' => $actor->can('sources.export'),
            'import' => $actor->can('sources.import'),
            // Gates the ActivityLogSection in the detail (spec 0034); the
            // record-level `sources.view` boundary is enforced separately by
            // GET /api/activity-log/sources/{id} itself.
            'view_activity' => $model !== null && $actor->can('sources.viewActivity'),
        ];
    }
}

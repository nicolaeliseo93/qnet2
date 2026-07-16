<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * ResourceAuthorization for the `tags` resource (spec 0019).
 *
 * No contextual rules: every field's ceiling is simply visible+editable when
 * the actor may write (create/update), else visible+readonly, mirroring
 * SourcesAuthorization.
 */
class TagsAuthorization extends AbstractResourceAuthorization
{
    public function __construct(FieldPermissionRepository $fieldPermissionRepository)
    {
        parent::__construct($fieldPermissionRepository);
    }

    public function resource(): string
    {
        return 'tags';
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
            'delete' => $model !== null && $actor->can('tags.delete'),
            'export' => $actor->can('tags.export'),
            'import' => $actor->can('tags.import'),
            // Gates the ActivityLogSection in the detail (spec 0034); the
            // record-level `tags.view` boundary is enforced separately by
            // GET /api/activity-log/tags/{id} itself.
            'view_activity' => $model !== null && $actor->can('tags.viewActivity'),
        ];
    }
}

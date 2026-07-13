<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * ResourceAuthorization for the `projects` resource (spec 0023).
 *
 * No contextual rules: every field's ceiling is simply visible+editable when
 * the actor may write (create/update), else visible+readonly. `code` is
 * intentionally NOT a catalogue field: it is server-generated (BR-1), never
 * submitted, so it carries no field-permission entry.
 */
class ProjectsAuthorization extends AbstractResourceAuthorization
{
    public function __construct(FieldPermissionRepository $fieldPermissionRepository)
    {
        parent::__construct($fieldPermissionRepository);
    }

    public function resource(): string
    {
        return 'projects';
    }

    /**
     * @return array<int, FieldDefinition>
     */
    public function fields(): array
    {
        return [
            new FieldDefinition('name', 'text', mandatory: true),
            new FieldDefinition('description', 'textarea'),
            new FieldDefinition('registry_id', 'select'),
            new FieldDefinition('project_status_id', 'select', mandatory: true),
            new FieldDefinition('source_id', 'select'),
            new FieldDefinition('business_function_id', 'select'),
            new FieldDefinition('state_id', 'select'),
            new FieldDefinition('product_category_id', 'select'),
            new FieldDefinition('partner_id', 'select'),
            new FieldDefinition('start_date', 'date'),
            new FieldDefinition('end_date', 'date'),
            new FieldDefinition('total_budget', 'number'),
            new FieldDefinition('target_lead', 'number'),
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
     * @return array<string, FieldPermission>
     */
    protected function fieldPermissionCeiling(User $actor, ?Model $model): array
    {
        $mayWrite = $this->actorMayWrite($actor, $model);

        return [
            'name' => $mayWrite ? FieldPermission::visibleEditable(required: true) : FieldPermission::visibleReadonly(),
            'description' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'registry_id' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'project_status_id' => $mayWrite ? FieldPermission::visibleEditable(required: true) : FieldPermission::visibleReadonly(),
            'source_id' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'business_function_id' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'state_id' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'product_category_id' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'partner_id' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'start_date' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'end_date' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'total_budget' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'target_lead' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
        ];
    }

    /**
     * @return array<string, bool>
     */
    public function actionPermissions(User $actor, ?Model $model): array
    {
        /** @var Project|null $model */
        return [
            'delete' => $model !== null && $actor->can('projects.delete'),
            'export' => $actor->can('projects.export'),
            'import' => $actor->can('projects.import'),
        ];
    }
}

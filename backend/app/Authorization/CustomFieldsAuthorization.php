<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * ResourceAuthorization for the `custom-fields` admin resource (spec 0021 —
 * ADMIN CRUD DEFINIZIONI): governs the CustomFieldDefinition catalogue
 * itself, not the entities it extends.
 *
 * No contextual rules: every field's ceiling is simply visible+editable when
 * the actor may write (create/update), else visible+readonly, mirroring
 * AttributesAuthorization/ProductCategoriesAuthorization. `options`,
 * `config`, `validation` and `relation_target` are nested, custom-rendered
 * editors (see FieldTypeHandler::toMeta() for their per-type shape), never a
 * plain scalar input.
 */
class CustomFieldsAuthorization extends AbstractResourceAuthorization
{
    public function __construct(FieldPermissionRepository $fieldPermissionRepository)
    {
        parent::__construct($fieldPermissionRepository);
    }

    public function resource(): string
    {
        return 'custom-fields';
    }

    /**
     * @return array<int, FieldDefinition>
     */
    public function fields(): array
    {
        return [
            new FieldDefinition('entity_type', 'select', mandatory: true),
            new FieldDefinition('key', 'text', mandatory: true),
            new FieldDefinition('type', 'select', mandatory: true),
            new FieldDefinition('label', 'text', mandatory: true),
            new FieldDefinition('description', 'textarea'),
            new FieldDefinition('help_text', 'textarea'),
            new FieldDefinition('placeholder', 'text'),
            new FieldDefinition('icon', 'text'),
            new FieldDefinition('group', 'text'),
            new FieldDefinition('tab', 'text'),
            new FieldDefinition('sort_order', 'number'),
            new FieldDefinition('config', 'custom'),
            new FieldDefinition('validation', 'custom'),
            new FieldDefinition('relation_target', 'custom'),
            new FieldDefinition('is_indexed', 'boolean'),
            new FieldDefinition('is_active', 'boolean'),
            new FieldDefinition('options', 'custom'),
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
        $writable = static fn (bool $required = false): FieldPermission => $mayWrite
            ? FieldPermission::visibleEditable(required: $required)
            : FieldPermission::visibleReadonly();

        return [
            'entity_type' => $writable(required: true),
            'key' => $writable(required: true),
            'type' => $writable(required: true),
            'label' => $writable(required: true),
            'description' => $writable(),
            'help_text' => $writable(),
            'placeholder' => $writable(),
            'icon' => $writable(),
            'group' => $writable(),
            'tab' => $writable(),
            'sort_order' => $writable(),
            'config' => $writable(),
            'validation' => $writable(),
            'relation_target' => $writable(),
            'is_indexed' => $writable(),
            'is_active' => $writable(),
            'options' => $writable(),
        ];
    }

    /**
     * @return array<string, bool>
     */
    public function actionPermissions(User $actor, ?Model $model): array
    {
        return [
            'delete' => $model !== null && $actor->can('custom-fields.delete'),
            'export' => $actor->can('custom-fields.export'),
            'import' => $actor->can('custom-fields.import'),
            // Gates the ActivityLogSection in the detail (spec 0034); the
            // record-level `custom-fields.view` boundary is enforced
            // separately by GET /api/activity-log/custom-fields/{id}.
            'view_activity' => $model !== null && $actor->can('custom-fields.viewActivity'),
        ];
    }
}

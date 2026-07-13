<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Models\Campaign;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * ResourceAuthorization for the `campaigns` resource (spec 0023).
 *
 * No contextual rules: every field's ceiling is simply visible+editable when
 * the actor may write (create/update), else visible+readonly, mirroring
 * ProjectsAuthorization. The BR-2 derivation (the 4 classification fields
 * forced null/required depending on `project_id`) is a VALUE-level rule
 * enforced by the FormRequest + CampaignService, not a field-permission
 * concern — this class only answers "may this actor touch this field at
 * all", same as every other resource. `code` is intentionally NOT a
 * catalogue field: it is server-generated (BR-1), never submitted.
 */
class CampaignsAuthorization extends AbstractResourceAuthorization
{
    public function __construct(FieldPermissionRepository $fieldPermissionRepository)
    {
        parent::__construct($fieldPermissionRepository);
    }

    public function resource(): string
    {
        return 'campaigns';
    }

    /**
     * @return array<int, FieldDefinition>
     */
    public function fields(): array
    {
        return [
            new FieldDefinition('project_id', 'select'),
            new FieldDefinition('name', 'text', mandatory: true),
            new FieldDefinition('description', 'textarea'),
            new FieldDefinition('registry_id', 'select'),
            new FieldDefinition('source_id', 'select'),
            new FieldDefinition('partner_id', 'select'),
            new FieldDefinition('project_status_id', 'select'),
            new FieldDefinition('business_function_id', 'select'),
            new FieldDefinition('state_id', 'select'),
            new FieldDefinition('product_category_id', 'select'),
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
            'project_id' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'name' => $mayWrite ? FieldPermission::visibleEditable(required: true) : FieldPermission::visibleReadonly(),
            'description' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'registry_id' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'source_id' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'partner_id' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'project_status_id' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'business_function_id' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'state_id' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'product_category_id' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
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
        /** @var Campaign|null $model */
        return [
            'delete' => $model !== null && $actor->can('campaigns.delete'),
            'export' => $actor->can('campaigns.export'),
            'import' => $actor->can('campaigns.import'),
        ];
    }
}

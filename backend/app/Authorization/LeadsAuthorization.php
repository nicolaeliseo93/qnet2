<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Models\Lead;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * ResourceAuthorization for the `leads` resource (spec 0024).
 *
 * No contextual rules: every field's ceiling is simply visible+editable when
 * the actor may write (create/update), else visible+readonly, mirroring
 * CampaignsAuthorization. `referent_id`/`campaign_id`/`lead_status_id` are
 * the mandatory fields (BR-1, spec 0029 D-1); no `code` field exists for a
 * Lead (D-3).
 */
class LeadsAuthorization extends AbstractResourceAuthorization
{
    public function __construct(FieldPermissionRepository $fieldPermissionRepository)
    {
        parent::__construct($fieldPermissionRepository);
    }

    public function resource(): string
    {
        return 'leads';
    }

    /**
     * @return array<int, FieldDefinition>
     */
    public function fields(): array
    {
        return [
            new FieldDefinition('referent_id', 'select', mandatory: true),
            new FieldDefinition('campaign_id', 'select', mandatory: true),
            new FieldDefinition('operational_site_id', 'select'),
            new FieldDefinition('source_id', 'select'),
            new FieldDefinition('operator_id', 'select'),
            new FieldDefinition('lead_status_id', 'select', mandatory: true),
            new FieldDefinition('notes', 'textarea'),
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
            'referent_id' => $mayWrite ? FieldPermission::visibleEditable(required: true) : FieldPermission::visibleReadonly(),
            'campaign_id' => $mayWrite ? FieldPermission::visibleEditable(required: true) : FieldPermission::visibleReadonly(),
            'operational_site_id' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'source_id' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'operator_id' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'lead_status_id' => $mayWrite ? FieldPermission::visibleEditable(required: true) : FieldPermission::visibleReadonly(),
            'notes' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
        ];
    }

    /**
     * @return array<string, bool>
     */
    public function actionPermissions(User $actor, ?Model $model): array
    {
        /** @var Lead|null $model */
        return [
            'delete' => $model !== null && $actor->can('leads.delete'),
            'export' => $actor->can('leads.export'),
            'import' => $actor->can('leads.import'),
        ];
    }
}

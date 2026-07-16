<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * ResourceAuthorization for the `companies` resource (spec 0010).
 *
 * No contextual rules (no protected system row): every field's ceiling is
 * simply visible+editable when the actor may write (create/update), else
 * visible+readonly, mirroring BusinessFunctionsAuthorization.
 * resourcePermissions() is the AbstractResourceAuthorization default (not
 * overridden). actionPermissions() IS overridden (spec 0034): `view_activity`
 * maps to `companies.viewActivity` (camelCase ability), which the default
 * "{resource}.{action}" mapping cannot express for an underscored action key.
 */
class CompaniesAuthorization extends AbstractResourceAuthorization
{
    public function __construct(FieldPermissionRepository $fieldPermissionRepository)
    {
        parent::__construct($fieldPermissionRepository);
    }

    public function resource(): string
    {
        return 'companies';
    }

    /**
     * @return array<int, FieldDefinition>
     */
    public function fields(): array
    {
        return [
            new FieldDefinition('denomination', 'text', mandatory: true),
            new FieldDefinition('vat_number', 'text'),
            new FieldDefinition('address', 'collection'),
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
            'denomination' => $mayWrite ? FieldPermission::visibleEditable(required: true) : FieldPermission::visibleReadonly(),
            'vat_number' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
            'address' => $mayWrite ? FieldPermission::visibleEditable() : FieldPermission::visibleReadonly(),
        ];
    }

    /**
     * @return array<string, bool>
     */
    public function actionPermissions(User $actor, ?Model $model): array
    {
        return [
            'delete' => $model !== null && $actor->can('companies.delete'),
            'export' => $actor->can('companies.export'),
            'import' => $actor->can('companies.import'),
            // Gates the ActivityLogSection in the detail (spec 0034); the
            // record-level `companies.view` boundary is enforced separately by
            // GET /api/activity-log/companies/{id} itself.
            'view_activity' => $model !== null && $actor->can('companies.viewActivity'),
        ];
    }
}

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
 * resourcePermissions()/actionPermissions() are the AbstractResourceAuthorization
 * defaults (not overridden): every standard ability -> "companies.{ability}".
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
        return ['delete', 'export', 'import'];
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
}

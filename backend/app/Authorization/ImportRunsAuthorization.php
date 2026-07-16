<?php

declare(strict_types=1);

namespace App\Authorization;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * ResourceAuthorization for the `import-runs` module (spec 0034). An import
 * run is never hand-edited: every field is visible+readonly regardless of the
 * actor's write abilities (the wizard, not a form, is how a run's data
 * changes), and the only actions are `delete` and `export` — there is no
 * `create`/`update` field-level surface to gate.
 *
 * Exposed by `GET /api/meta/import-runs`.
 */
class ImportRunsAuthorization extends AbstractResourceAuthorization
{
    public function resource(): string
    {
        return 'import-runs';
    }

    /**
     * @return array<int, FieldDefinition>
     */
    public function fields(): array
    {
        return [
            new FieldDefinition('original_filename', 'text'),
            new FieldDefinition('status', 'text'),
            new FieldDefinition('total_rows', 'number'),
            new FieldDefinition('imported_rows', 'number'),
            new FieldDefinition('invalid_rows', 'number'),
            new FieldDefinition('warning_rows', 'number'),
            new FieldDefinition('duplicate_rows', 'number'),
            new FieldDefinition('modified_rows', 'number'),
            new FieldDefinition('created_at', 'date'),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function actions(): array
    {
        return ['delete', 'export'];
    }

    /**
     * Every field is visible+readonly, unconditionally: an import run is
     * never edited through a form.
     *
     * @return array<string, FieldPermission>
     */
    protected function fieldPermissionCeiling(User $actor, ?Model $model): array
    {
        $readonly = FieldPermission::visibleReadonly();

        return array_fill_keys(
            array_map(static fn (FieldDefinition $field): string => $field->key, $this->fields()),
            $readonly,
        );
    }

    /**
     * @return array<string, bool>
     */
    public function actionPermissions(User $actor, ?Model $model): array
    {
        return [
            'delete' => $actor->can('import-runs.delete'),
            'export' => $actor->can('import-runs.export'),
        ];
    }
}

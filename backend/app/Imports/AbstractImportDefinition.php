<?php

namespace App\Imports;

use App\Models\User;
use Illuminate\Support\Facades\Gate;

/**
 * Shared resolution for every concrete ImportDefinition. Mirrors
 * App\Tables\AbstractTableDefinition: a concrete definition declares only its
 * model (modelClass()), its column catalogue (columns()) and the row-level
 * hooks (validateRow/dedupKey/existsInDatabase/createRow); the cross-cutting
 * parts (resource() default, fail-closed authorization, column-id/required-id
 * derivation) live here once.
 */
abstract class AbstractImportDefinition implements ImportDefinition
{
    public function resource(): string
    {
        return $this->domain();
    }

    /**
     * Fail-closed default authorization: delegate to the model's Policy
     * `import` ability (BasePolicy::import() → "{resource}.import").
     *
     * Mirrors AbstractTableDefinition::authorizeViewAny — Gate::allows()
     * returns false when no Policy/permission is registered, never fail-open.
     */
    public function authorizeImport(User $actor): bool
    {
        return Gate::forUser($actor)->allows('import', $this->modelClass());
    }

    /**
     * Column ids in declared order — the downloadable CSV template header.
     *
     * @return array<int, string>
     */
    public function columnIds(): array
    {
        return array_map(static fn (array $column): string => $column['id'], $this->columns());
    }

    /**
     * Column ids flagged required. Concrete definitions call this from
     * validateRow() to reject a row with a blank value on any of these,
     * instead of re-declaring the required-column loop themselves.
     *
     * @return array<int, string>
     */
    public function requiredColumnIds(): array
    {
        $ids = [];

        foreach ($this->columns() as $column) {
            if (($column['required'] ?? false) === true) {
                $ids[] = $column['id'];
            }
        }

        return $ids;
    }

    /**
     * Split a pipe-delimited (`|`) multi-value CSV cell into trimmed,
     * non-blank items. The delimiter for any column that itself packs a LIST
     * into a single CSV cell (the CSV field separator is already a comma),
     * e.g. `permissions`/`roles` (RolesImportDefinition/UsersImportDefinition).
     * A blank cell yields an empty list.
     *
     * @return array<int, string>
     */
    protected function splitPipeList(?string $raw): array
    {
        $raw = trim((string) $raw);

        if ($raw === '') {
            return [];
        }

        return array_values(array_filter(
            array_map('trim', explode('|', $raw)),
            static fn (string $value): bool => $value !== '',
        ));
    }
}

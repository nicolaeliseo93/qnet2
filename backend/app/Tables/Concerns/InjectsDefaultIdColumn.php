<?php

namespace App\Tables\Concerns;

/**
 * Injects a default hidden `id` column into every table that does not declare
 * its own, so the primary key is uniformly surfaceable across the grid, its
 * column-visibility preferences and the sort/filter whitelists — with no
 * per-domain declaration.
 *
 * The column is hidden by default (ADR-0004 visibility): the row payload
 * already carries `id` (every mapRow returns it), so the frontend can toggle it
 * on demand. A definition that needs different `id` semantics (e.g. the roles
 * table, which keeps it non-filterable) declares its own `id` column, which
 * wins — this is a default, not a mandate. The raw `columns()` catalogue (used
 * by export/migration) is left untouched: the default `id` is a grid/
 * preferences concern only.
 */
trait InjectsDefaultIdColumn
{
    /**
     * The declared columns with the default hidden `id` column prepended as the
     * FIRST column — but only when the definition does not already declare an
     * `id` column of its own.
     *
     * `id` is the leftmost column (once the user toggles it visible), matching
     * the tables that already declare their own `id` first (users, roles,
     * companies, …).
     *
     * @return array<int, array<string, mixed>>
     */
    private function columnsWithDefaultId(): array
    {
        $columns = $this->columns();

        foreach ($columns as $column) {
            if (($column['id'] ?? null) === 'id') {
                return $columns;
            }
        }

        return [$this->defaultIdColumn(), ...$columns];
    }

    /**
     * The default hidden `id` column: the primary key as a sortable column,
     * hidden by default. Not filterable — a filter widget on a technical id is
     * clutter; a table that wants it filterable declares its own `id` column
     * (as users/companies do), which wins over this default.
     *
     * @return array<string, mixed>
     */
    private function defaultIdColumn(): array
    {
        return [
            'id' => 'id',
            'label' => 'table.columns.id',
            'type' => 'number',
            'visible' => false,
            'sortable' => true,
            'filterable' => false,
            'filterType' => null,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    abstract public function columns(): array;
}

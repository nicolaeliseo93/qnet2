<?php

namespace Tests\Stubs;

use App\Models\BusinessFunction;
use App\Models\User;
use App\Tables\AbstractTableDefinition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Minimal, test-only TableDefinition backing the export engine's test suite
 * (spec 0014): a controlled column catalogue covering every
 * ExportValueFormatter branch (number/text/boolean/datetime/tags), on top of
 * the REAL `business_functions` table — reusing its existing Policy/
 * permissions (`business-functions.*`) under a fake `stub-exports` domain
 * key, exactly like the import stub definitions reuse them.
 */
class StubExportTableDefinition extends AbstractTableDefinition
{
    public function domain(): string
    {
        return 'stub-exports';
    }

    /**
     * @return class-string<BusinessFunction>
     */
    public function modelClass(): string
    {
        return BusinessFunction::class;
    }

    /**
     * @return Builder<BusinessFunction>
     */
    public function baseQuery(): Builder
    {
        return BusinessFunction::query();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function columns(): array
    {
        return [
            ['id' => 'id', 'label' => 'ID', 'type' => 'number', 'visible' => true, 'sortable' => true, 'filterable' => true, 'filterType' => 'number'],
            ['id' => 'name', 'label' => 'Name', 'type' => 'text', 'visible' => true, 'sortable' => true, 'filterable' => true, 'filterType' => 'text', 'searchable' => true],
            ['id' => 'is_business_unit', 'label' => 'Business unit', 'type' => 'boolean', 'visible' => true, 'sortable' => true, 'filterable' => true, 'filterType' => 'set'],
            ['id' => 'created_at', 'label' => 'Created', 'type' => 'datetime', 'visible' => true, 'sortable' => true, 'filterable' => true, 'filterType' => 'date'],
            // Derived: no real DB column, exercises ExportValueFormatter's
            // array/tags join('; ') branch.
            ['id' => 'tags', 'label' => 'Tags', 'type' => 'tags', 'visible' => true, 'sortable' => false, 'filterable' => false],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filters(): array
    {
        return [
            ['columnId' => 'name', 'type' => 'text'],
            ['columnId' => 'is_business_unit', 'type' => 'set'],
            ['columnId' => 'created_at', 'type' => 'date'],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function actions(): array
    {
        return [];
    }

    /**
     * @return array<int, array{columnId: string, direction: string}>
     */
    public function defaultSort(): array
    {
        return [['columnId' => 'id', 'direction' => 'asc']];
    }

    /**
     * @return array{limit: int}
     */
    public function defaultPagination(): array
    {
        return ['limit' => 25];
    }

    /**
     * @return array<string, mixed>
     */
    public function mapRow(User $actor, Model $row): array
    {
        /** @var BusinessFunction $row */
        return [
            'id' => $row->id,
            'name' => $row->name,
            'is_business_unit' => $row->is_business_unit,
            'created_at' => $row->created_at,
            'tags' => $row->name === '' ? [] : explode(' ', (string) $row->name),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function actionsFor(User $actor, Model $row): array
    {
        return [];
    }
}

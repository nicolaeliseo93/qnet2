<?php

namespace Tests\Stubs;

use App\Enums\AdvancedFilterType;
use App\Models\BusinessFunction;
use App\Models\User;
use App\Tables\AbstractTableDefinition;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Minimal, test-only TableDefinition backing the advanced-filters framework
 * suite (spec 0032): a controlled advancedFilters() catalogue covering every
 * generic AdvancedFilterApplier branch — direct column (text/number_range/
 * date_range/multiselect/checkbox) and generic relation (single via
 * `manager`, multi via `users`) — on top of the REAL `business_functions`
 * table, reusing its Policy/permissions under a fake `stub-advanced-filters`
 * domain key, exactly like StubExportTableDefinition (spec 0014).
 */
class StubAdvancedFilterTableDefinition extends AbstractTableDefinition
{
    public function domain(): string
    {
        return 'stub-advanced-filters';
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
            ['id' => 'id', 'label' => 'ID', 'type' => 'number', 'visible' => true, 'sortable' => true, 'filterable' => false],
            ['id' => 'name', 'label' => 'Name', 'type' => 'text', 'visible' => true, 'sortable' => true, 'filterable' => false, 'searchable' => true],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function filters(): array
    {
        return [];
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
        return ['id' => $row->id, 'name' => $row->name];
    }

    /**
     * @return array<int, string>
     */
    public function actionsFor(User $actor, Model $row): array
    {
        return [];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function advancedFilters(): array
    {
        return [
            [
                'name' => 'name',
                'label' => 'Name',
                'type' => AdvancedFilterType::Text,
                'order' => 1,
                'required' => false,
                'visible' => true,
                'width' => 'md',
                'multiple' => false,
                'target' => 'name',
            ],
            [
                'name' => 'id_range',
                'label' => 'ID range',
                'type' => AdvancedFilterType::NumberRange,
                'order' => 2,
                'required' => false,
                'visible' => true,
                'width' => 'sm',
                'multiple' => false,
                'target' => 'id',
            ],
            [
                'name' => 'created_range',
                'label' => 'Created',
                'type' => AdvancedFilterType::DateRange,
                'order' => 3,
                'required' => false,
                'visible' => true,
                'width' => 'md',
                'multiple' => false,
                'target' => 'created_at',
            ],
            [
                'name' => 'name_in',
                'label' => 'Name in',
                'type' => AdvancedFilterType::Multiselect,
                'order' => 4,
                'required' => false,
                'visible' => true,
                'width' => 'md',
                'multiple' => true,
                'target' => 'name',
            ],
            [
                'name' => 'is_unit',
                'label' => 'Is business unit',
                'type' => AdvancedFilterType::Checkbox,
                'order' => 5,
                'required' => true,
                'defaultValue' => false,
                'visible' => true,
                'width' => 'sm',
                'multiple' => false,
                'target' => 'is_business_unit',
            ],
            [
                'name' => 'manager',
                'label' => 'Manager',
                'type' => AdvancedFilterType::Relation,
                'order' => 6,
                'required' => false,
                'visible' => true,
                'width' => 'md',
                'multiple' => false,
                'source' => ['resource' => 'users'],
                'target' => 'manager',
            ],
            [
                'name' => 'users',
                'label' => 'Users',
                'type' => AdvancedFilterType::Relation,
                'order' => 7,
                'required' => false,
                'visible' => true,
                'width' => 'md',
                'multiple' => true,
                'source' => ['resource' => 'users'],
                'target' => 'users',
            ],
        ];
    }
}

<?php

namespace Tests\Stubs;

use App\Enums\AdvancedFilterType;
use App\Models\BusinessFunction;
use App\Services\Table\FilterApplier;
use Illuminate\Database\Eloquent\Builder;

/**
 * Sibling of StubAdvancedFilterTableDefinition adding ONE genuinely derived
 * advanced filter (`manager_name`, spec 0032 AC-007): searching a related
 * model by a non-id field (the manager's `name`) cannot be expressed by the
 * generic relation-by-id path, so it requires overriding
 * `applyAdvancedFilter()` — checking its own name first, then falling back to
 * `parent::applyAdvancedFilter()` for every other (direct-column/generic
 * relation) filter, exactly like `leads`' referent/campaign filters will.
 */
class StubAdvancedFilterOverrideTableDefinition extends StubAdvancedFilterTableDefinition
{
    public function domain(): string
    {
        return 'stub-advanced-filters-override';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function advancedFilters(): array
    {
        return [
            ...parent::advancedFilters(),
            [
                'name' => 'manager_name',
                'label' => 'Manager name',
                'type' => AdvancedFilterType::Text,
                'order' => 8,
                'required' => false,
                'visible' => true,
                'width' => 'md',
                'multiple' => false,
                // Internal only: the relation name the override searches,
                // never a real column on business_functions.
                'target' => 'manager',
            ],
        ];
    }

    /**
     * @param  Builder<BusinessFunction>  $query
     * @param  array<string, mixed>  $descriptor
     */
    public function applyAdvancedFilter(Builder $query, string $name, array $descriptor, mixed $value): bool
    {
        if ($name === 'manager_name') {
            if (! is_string($value) || $value === '') {
                return true;
            }

            $escaped = app(FilterApplier::class)->escapeLike($value);

            $query->whereHas('manager', function (Builder $related) use ($escaped): void {
                $related->where('name', 'like', '%'.$escaped.'%');
            });

            return true;
        }

        return parent::applyAdvancedFilter($query, $name, $descriptor, $value);
    }
}

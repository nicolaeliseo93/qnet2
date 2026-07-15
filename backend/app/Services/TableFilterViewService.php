<?php

namespace App\Services;

use App\Enums\FilterViewVisibility;
use App\Models\TableFilterView;
use App\Models\User;
use App\Tables\TableDefinition;
use Illuminate\Database\Eloquent\Collection;

/**
 * Saved filter views (spec 0007): list (own + others' shared), create, update,
 * delete. Domain-agnostic: operates ONLY through the resolved TableDefinition
 * and the generic table_filter_views store, so every table inherits saved
 * views with no per-domain code.
 *
 * SECURITY: `filters` is restricted to the definition's FILTERABLE columns on
 * every write (defensive re-filter — TableFilterViewRequest already 422s any
 * out-of-whitelist key) AND on every read, so a stale/removed column can never
 * leak back into the grid and a stored view can never widen the SSRM filter
 * allow-list (mirrors TableFilterStateService).
 */
class TableFilterViewService
{
    /**
     * The actor's own views (private + shared) plus other users' `shared`
     * views for the domain. Order: owned first, then shared-by-others; each
     * group by name asc — achieved with a stable sort over a name-ordered
     * query (no raw SQL needed for the boolean "owned" grouping).
     *
     * @return Collection<int, TableFilterView>
     */
    public function list(TableDefinition $definition, User $actor): Collection
    {
        $views = TableFilterView::query()
            ->with('user')
            ->where('domain', $definition->domain())
            ->where(function ($query) use ($actor): void {
                $query->where('user_id', $actor->id)
                    ->orWhere('visibility', FilterViewVisibility::Shared->value);
            })
            ->orderBy('name')
            ->get();

        $sorted = $views->sortBy(fn (TableFilterView $view): int => $view->user_id === $actor->id ? 0 : 1)
            ->values();

        return $sorted->each(fn (TableFilterView $view) => $this->reFilter($definition, $view));
    }

    /**
     * Create a new view owned by $actor.
     *
     * @param  array<string, mixed>  $filters
     * @param  array<string, mixed>  $advancedFilters
     */
    public function create(
        TableDefinition $definition,
        User $actor,
        string $name,
        array $filters,
        FilterViewVisibility $visibility,
        array $advancedFilters = [],
    ): TableFilterView {
        $view = TableFilterView::query()->create([
            'user_id' => $actor->id,
            'domain' => $definition->domain(),
            'name' => $name,
            'filters' => $this->allowlist($definition, $filters),
            'visibility' => $visibility,
            'advanced_filters' => $this->allowlistAdvanced($definition, $advancedFilters),
        ]);

        return $this->reFilter($definition, $view);
    }

    /**
     * Update an existing view (full replace of name/filters/visibility/
     * advanced filters).
     *
     * @param  array<string, mixed>  $filters
     * @param  array<string, mixed>  $advancedFilters
     */
    public function update(
        TableDefinition $definition,
        TableFilterView $view,
        string $name,
        array $filters,
        FilterViewVisibility $visibility,
        array $advancedFilters = [],
    ): TableFilterView {
        $view->update([
            'name' => $name,
            'filters' => $this->allowlist($definition, $filters),
            'visibility' => $visibility,
            'advanced_filters' => $this->allowlistAdvanced($definition, $advancedFilters),
        ]);

        return $this->reFilter($definition, $view->refresh());
    }

    public function delete(TableFilterView $view): void
    {
        $view->delete();
    }

    /**
     * Re-filter a fetched view's `filters` in place to the definition's
     * current filterable allow-list, so a removed/renamed column never
     * reaches the frontend even for a view saved before the change.
     */
    private function reFilter(TableDefinition $definition, TableFilterView $view): TableFilterView
    {
        $view->filters = $this->allowlist($definition, $view->filters ?? []);
        $view->advanced_filters = $this->allowlistAdvanced($definition, $view->advanced_filters ?? []);

        return $view;
    }

    /**
     * Keep only the entries whose column id is whitelisted for filtering by
     * the definition — the same allow-list the SSRM query engine enforces.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function allowlist(TableDefinition $definition, array $filters): array
    {
        $allowed = array_flip($definition->filterableColumnIds());

        return array_intersect_key($filters, $allowed);
    }

    /**
     * Keep only the entries whose name is whitelisted in the definition's
     * advanced-filter catalogue (spec 0032) — mirrors allowlist().
     *
     * @param  array<string, mixed>  $advancedFilters
     * @return array<string, mixed>
     */
    private function allowlistAdvanced(TableDefinition $definition, array $advancedFilters): array
    {
        $allowed = array_flip($definition->advancedFilterableIds());

        return array_intersect_key($advancedFilters, $allowed);
    }
}

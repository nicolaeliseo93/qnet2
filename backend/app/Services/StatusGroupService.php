<?php

namespace App\Services;

use App\DataObjects\Shared\ForSelectQuery;
use App\DataObjects\Shared\ForSelectResult;
use App\DataObjects\StatusGroups\CreateStatusGroupData;
use App\DataObjects\StatusGroups\UpdateStatusGroupData;
use App\Models\StatusGroup;
use Illuminate\Support\Collection;

/**
 * Business logic for the `status-groups` resource (spec 0039, D-6): a
 * full-CRUD, globally-shared lookup (name/color/sort_order) classifying the
 * custom rows of both status configurators. Mirrors LeadStatusService's
 * shape, including the delete guard — except here a group can be referenced
 * by EITHER pipeline_statuses OR lead_statuses (two distinct relations,
 * unlike a status's single referencing model).
 */
class StatusGroupService
{
    public function create(CreateStatusGroupData $data): StatusGroup
    {
        return StatusGroup::create($data->attributes());
    }

    public function update(StatusGroup $statusGroup, UpdateStatusGroupData $data): StatusGroup
    {
        $attributes = $data->submittedAttributes();

        // Unconditional save: fire the model's saved event even when no native
        // attribute changed, so the HasCustomFields write pipeline (spec 0021)
        // persists a custom-fields-only edit. A clean save runs no UPDATE query.
        $statusGroup->fill($attributes)->save();

        return $statusGroup->fresh();
    }

    /**
     * A group referenced by at least one pipeline status OR lead status
     * cannot be removed (it would silently orphan them). Defense in depth:
     * the FK is also restrictOnDelete at the schema layer on both tables.
     */
    public function delete(StatusGroup $statusGroup): void
    {
        if ($statusGroup->pipelineStatuses()->exists() || $statusGroup->leadStatuses()->exists()) {
            abort(409, 'This status group is used by a status and cannot be deleted.');
        }

        $statusGroup->delete();
    }

    /**
     * Minimal, searchable, paginated status group list for the for-select
     * standard (ADR 0011), mirroring LeadStatusService::forSelect. Ordered by
     * `sort_order` first so the select mirrors the table's display order.
     */
    public function forSelect(ForSelectQuery $query): ForSelectResult
    {
        $base = StatusGroup::query()->select(['id', 'name']);

        if ($query->hasSearch()) {
            $base->where('name', 'like', '%'.$query->search.'%');
        }

        $total = (clone $base)->count();

        /** @var Collection<int, StatusGroup> $page */
        $page = $base->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('id')
            ->offset($query->offset)
            ->limit($query->limit)
            ->get();

        $items = $this->appendHydratedIds($page, $query);

        return new ForSelectResult(
            items: $items,
            total: $total,
            offset: $query->offset,
            limit: $query->limit,
        );
    }

    /**
     * Append the explicitly-requested `ids[]` (edit-mode hydration) that are
     * not already on the page, deduplicated. They bypass search and the same
     * id/name projection applies. Total is unaffected.
     *
     * @param  Collection<int, StatusGroup>  $page
     * @return Collection<int, StatusGroup>
     */
    private function appendHydratedIds(Collection $page, ForSelectQuery $query): Collection
    {
        if (! $query->hasIds()) {
            return $page;
        }

        $presentIds = $page->pluck('id')->all();
        $missingIds = array_values(array_diff($query->ids, $presentIds));

        if ($missingIds === []) {
            return $page;
        }

        /** @var Collection<int, StatusGroup> $hydrated */
        $hydrated = StatusGroup::query()
            ->select(['id', 'name'])
            ->whereIn('id', $missingIds)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return $page->concat($hydrated);
    }
}

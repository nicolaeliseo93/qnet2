<?php

namespace App\Services;

use App\DataObjects\LeadStatuses\CreateLeadStatusData;
use App\DataObjects\LeadStatuses\UpdateLeadStatusData;
use App\DataObjects\Shared\ForSelectQuery;
use App\DataObjects\Shared\ForSelectResult;
use App\Models\LeadStatus;
use Illuminate\Support\Collection;

/**
 * Business logic for the `lead-statuses` resource (spec 0029): a full-CRUD
 * lookup entity (name/color/sort_order) describing a Lead's working state.
 * Mirrors PipelineStatusService's shape, including the BR-3 delete guard.
 */
class LeadStatusService
{
    public function create(CreateLeadStatusData $data): LeadStatus
    {
        return LeadStatus::create($data->attributes());
    }

    public function update(LeadStatus $leadStatus, UpdateLeadStatusData $data): LeadStatus
    {
        $attributes = $data->submittedAttributes();

        // Unconditional save: fire the model's saved event even when no native
        // attribute changed, so the HasCustomFields write pipeline (spec 0021)
        // persists a custom-fields-only edit. A clean save runs no UPDATE query.
        $leadStatus->fill($attributes)->save();

        return $leadStatus->fresh();
    }

    /**
     * BR-3 (delete-guard): a status referenced by at least one Lead cannot be
     * removed (it would silently orphan them). Defense in depth: the FK is
     * also restrictOnDelete at the schema layer.
     */
    public function delete(LeadStatus $leadStatus): void
    {
        if ($leadStatus->leads()->exists()) {
            abort(409, 'This lead status is used by a lead and cannot be deleted.');
        }

        $leadStatus->delete();
    }

    /**
     * Minimal, searchable, paginated lead status list for the for-select
     * standard (ADR 0011), mirroring PipelineStatusService::forSelect. Ordered
     * by `sort_order` first so the select mirrors the table's display order
     * (BR-4).
     */
    public function forSelect(ForSelectQuery $query): ForSelectResult
    {
        $base = LeadStatus::query()->select(['id', 'name']);

        if ($query->hasSearch()) {
            $base->where('name', 'like', '%'.$query->search.'%');
        }

        $total = (clone $base)->count();

        /** @var Collection<int, LeadStatus> $page */
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
     * @param  Collection<int, LeadStatus>  $page
     * @return Collection<int, LeadStatus>
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

        /** @var Collection<int, LeadStatus> $hydrated */
        $hydrated = LeadStatus::query()
            ->select(['id', 'name'])
            ->whereIn('id', $missingIds)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return $page->concat($hydrated);
    }
}

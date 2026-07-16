<?php

namespace App\Services;

use App\DataObjects\LeadStatuses\CreateLeadStatusData;
use App\DataObjects\LeadStatuses\UpdateLeadStatusData;
use App\DataObjects\Shared\ForSelectQuery;
use App\DataObjects\Shared\ForSelectResult;
use App\Models\LeadStatus;
use App\Services\Statuses\StatusOrderManager;
use App\Services\Statuses\SystemStatusGuard;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Business logic for the `lead-statuses` resource (spec 0029): a full-CRUD
 * lookup entity (name/color) describing a Lead's working state. Mirrors
 * PipelineStatusService's shape, including the BR-3 delete guard.
 *
 * spec 0039: `sort_order` (D-5) is server-managed — placed by
 * StatusOrderManager::placeNew() on create, resequenced by reorder(); the two
 * mandatory system rows ("Nuovo"/"Chiuso", D-2) are protected by
 * SystemStatusGuard on both update() and delete().
 */
class LeadStatusService
{
    public function __construct(
        private readonly StatusOrderManager $orderManager,
        private readonly SystemStatusGuard $systemStatusGuard,
    ) {}

    /**
     * Shared by show (controller)/create/update — a hook point kept for
     * symmetry with PipelineStatusService even though `group` (spec 0039
     * pivot) is a plain column, needing no eager-load.
     */
    public function loadDetail(LeadStatus $leadStatus): LeadStatus
    {
        return $leadStatus;
    }

    public function create(CreateLeadStatusData $data): LeadStatus
    {
        $sortOrder = $this->orderManager->placeNew(LeadStatus::class);

        $leadStatus = LeadStatus::create([...$data->attributes(), 'sort_order' => $sortOrder]);

        return $this->loadDetail($leadStatus);
    }

    public function update(LeadStatus $leadStatus, UpdateLeadStatusData $data): LeadStatus
    {
        $attributes = $data->submittedAttributes();

        $this->systemStatusGuard->assertUpdatable($leadStatus, $attributes);

        // Unconditional save: fire the model's saved event even when no native
        // attribute changed, so the HasCustomFields write pipeline (spec 0021)
        // persists a custom-fields-only edit. A clean save runs no UPDATE query.
        $leadStatus->fill($attributes)->save();

        return $this->loadDetail($leadStatus->fresh());
    }

    /**
     * BR-3 (delete-guard): a status referenced by at least one Lead cannot be
     * removed (it would silently orphan them). Defense in depth: the FK is
     * also restrictOnDelete at the schema layer. The system-row guard (spec
     * 0039, D-2) runs FIRST: a system row is never deletable regardless of
     * whether it happens to be unreferenced.
     */
    public function delete(LeadStatus $leadStatus): void
    {
        $this->systemStatusGuard->assertDeletable($leadStatus);

        if ($leadStatus->leads()->exists()) {
            abort(409, 'This lead status is used by a lead and cannot be deleted.');
        }

        $leadStatus->delete();
    }

    /**
     * Resequences every custom row to $orderedIds' order and returns the
     * fresh, complete, ordered list (spec 0039, D-5). See
     * StatusOrderManager::reorder() for the validation/renormalization rules.
     *
     * @param  array<int, int>  $orderedIds
     * @return EloquentCollection<int, LeadStatus>
     */
    public function reorder(array $orderedIds): EloquentCollection
    {
        /** @var EloquentCollection<int, LeadStatus> $reordered */
        $reordered = $this->orderManager->reorder(LeadStatus::class, $orderedIds);

        return $reordered;
    }

    /**
     * Minimal, searchable, paginated lead status list for the for-select
     * standard (ADR 0011), mirroring PipelineStatusService::forSelect. Ordered
     * by `sort_order` first so the select mirrors the table's display order
     * (BR-4).
     */
    public function forSelect(ForSelectQuery $query): ForSelectResult
    {
        $base = LeadStatus::query()->select(['id', 'name', 'system_key']);

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
            ->select(['id', 'name', 'system_key'])
            ->whereIn('id', $missingIds)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return $page->concat($hydrated);
    }
}

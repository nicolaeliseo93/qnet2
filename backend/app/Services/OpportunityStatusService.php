<?php

namespace App\Services;

use App\DataObjects\OpportunityStatuses\CreateOpportunityStatusData;
use App\DataObjects\OpportunityStatuses\UpdateOpportunityStatusData;
use App\DataObjects\Shared\ForSelectQuery;
use App\DataObjects\Shared\ForSelectResult;
use App\Models\OpportunityStatus;
use App\Services\Statuses\StatusOrderManager;
use App\Services\Statuses\SystemStatusGuard;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;

/**
 * Business logic for the `opportunity-statuses` resource (spec 0043): a
 * full-CRUD lookup entity (name/color) describing an Opportunity's working
 * state. Keeps the shared lookup-service shape, including the BR-2 delete
 * guard.
 *
 * `sort_order` is server-managed — placed by StatusOrderManager::placeNew()
 * on create, resequenced by reorder(); the three mandatory system rows
 * ("Nuova"/"Chiusa con successo"/"Persa", D-2) are protected by
 * SystemStatusGuard on both update() and delete().
 */
class OpportunityStatusService
{
    public function __construct(
        private readonly StatusOrderManager $orderManager,
        private readonly SystemStatusGuard $systemStatusGuard,
    ) {}

    /**
     * Shared by show (controller)/create/update — a hook point kept for
     * symmetry with other status services even though `group` is a plain
     * column, needing no eager-load.
     */
    public function loadDetail(OpportunityStatus $opportunityStatus): OpportunityStatus
    {
        return $opportunityStatus;
    }

    public function create(CreateOpportunityStatusData $data): OpportunityStatus
    {
        $sortOrder = $this->orderManager->placeNew(OpportunityStatus::class);

        $opportunityStatus = OpportunityStatus::create([...$data->attributes(), 'sort_order' => $sortOrder]);

        return $this->loadDetail($opportunityStatus);
    }

    public function update(OpportunityStatus $opportunityStatus, UpdateOpportunityStatusData $data): OpportunityStatus
    {
        $attributes = $data->submittedAttributes();

        $this->systemStatusGuard->assertUpdatable($opportunityStatus, $attributes);

        // Unconditional save: fire the model's saved event even when no native
        // attribute changed, so the HasCustomFields write pipeline (spec 0021)
        // persists a custom-fields-only edit. A clean save runs no UPDATE query.
        $opportunityStatus->fill($attributes)->save();

        return $this->loadDetail($opportunityStatus->fresh());
    }

    /**
     * BR-2 (delete-guard): a status referenced by at least one Opportunity
     * cannot be removed (it would silently orphan them). Defense in depth:
     * the FK is also restrictOnDelete at the schema layer. The system-row
     * guard (spec 0043, D-2) runs FIRST: a system row is never deletable
     * regardless of whether it happens to be unreferenced.
     */
    public function delete(OpportunityStatus $opportunityStatus): void
    {
        $this->systemStatusGuard->assertDeletable($opportunityStatus);

        if ($opportunityStatus->opportunities()->exists()) {
            abort(409, 'This opportunity status is used by an opportunity and cannot be deleted.');
        }

        $opportunityStatus->delete();
    }

    /**
     * Resequences every custom row to $orderedIds' order and returns the
     * fresh, complete, ordered list (spec 0039, D-5). See
     * StatusOrderManager::reorder() for the validation/renormalization rules.
     *
     * @param  array<int, int>  $orderedIds
     * @return EloquentCollection<int, OpportunityStatus>
     */
    public function reorder(array $orderedIds): EloquentCollection
    {
        /** @var EloquentCollection<int, OpportunityStatus> $reordered */
        $reordered = $this->orderManager->reorder(OpportunityStatus::class, $orderedIds);

        return $reordered;
    }

    /**
     * Minimal, searchable, paginated opportunity status list for the
     * for-select standard (ADR 0011).
     * Ordered by `sort_order` first so the select mirrors the table's
     * display order (BR-7).
     */
    public function forSelect(ForSelectQuery $query): ForSelectResult
    {
        $base = OpportunityStatus::query()->select(['id', 'name', 'system_key']);

        if ($query->hasSearch()) {
            $base->where('name', 'like', '%'.$query->search.'%');
        }

        $total = (clone $base)->count();

        /** @var Collection<int, OpportunityStatus> $page */
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
     * @param  Collection<int, OpportunityStatus>  $page
     * @return Collection<int, OpportunityStatus>
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

        /** @var Collection<int, OpportunityStatus> $hydrated */
        $hydrated = OpportunityStatus::query()
            ->select(['id', 'name', 'system_key'])
            ->whereIn('id', $missingIds)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->orderBy('id')
            ->get();

        return $page->concat($hydrated);
    }
}

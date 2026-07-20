<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Leads\ConvertLeadToOpportunity;
use App\DataObjects\Leads\CreateLeadData;
use App\DataObjects\Leads\UpdateLeadData;
use App\DataObjects\Shared\ForSelectQuery;
use App\DataObjects\Shared\ForSelectResult;
use App\Models\Lead;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Business logic for the `leads` resource (spec 0024): plain create/update/
 * delete. No sequential code (D-3, unlike Project/Campaign); the cross-entity
 * guard blocking cancellation of a lead's OWN referenced entities (BR-2/D-4)
 * lives in the 5 REFERENCED modules' Services, not here (see
 * CampaignService/RegistryService/OperationalSiteService/SourceService/
 * UserService — spec 0041 D-1: the contact guard moved from Referent to
 * Registry). delete() carries its own guard for the INVERSE direction
 * (spec 0040, BR-3): a lead with a linked opportunity cannot itself be
 * removed.
 */
class LeadService
{
    /**
     * Relations eager-loaded for the detail read tree (LeadResource), so a
     * single query never N+1s.
     *
     * @var array<int, string>
     */
    private const array DETAIL_RELATIONS = [
        'registry',
        'campaign',
        'operationalSite.addresses.city',
        'source',
        'operator',
        // spec 0040: LeadResource.opportunity {id,name}|null.
        'opportunity',
    ];

    public function __construct(
        private readonly ConvertLeadToOpportunity $converter,
    ) {}

    public function loadDetail(Lead $lead): Lead
    {
        return $lead->load(self::DETAIL_RELATIONS);
    }

    /**
     * Plain create, or — when `convertToOpportunity` is set (spec 0044) — the
     * Lead and its derived Opportunity created atomically in a single
     * transaction: the conversion logic itself lives entirely in
     * ConvertLeadToOpportunity, not unrolled here.
     */
    public function create(CreateLeadData $data): Lead
    {
        if (! $data->convertToOpportunity) {
            $lead = Lead::create($data->attributes());

            return $this->loadDetail($lead);
        }

        $lead = DB::transaction(function () use ($data): Lead {
            $lead = Lead::create($data->attributes());
            $this->converter->handle($lead);

            return $lead;
        });

        return $this->loadDetail($lead);
    }

    public function update(Lead $lead, UpdateLeadData $data): Lead
    {
        // Unconditional save: fire the model's saved event even when no
        // native attribute changed, so the HasCustomFields write pipeline
        // (spec 0021) persists a custom-fields-only edit.
        $lead->fill($data->submittedAttributes())->save();

        return $this->loadDetail($lead);
    }

    /**
     * Restrictive delete (spec 0040, BR-3): a lead with a linked opportunity
     * cannot be removed.
     */
    public function delete(Lead $lead): void
    {
        if ($lead->opportunity()->exists()) {
            abort(409, 'This lead has a linked opportunity and cannot be deleted.');
        }

        $lead->delete();
    }

    /**
     * Minimal, searchable, paginated lead list for the for-select standard
     * (amendment rev.1 A-1, ADR 0011) — a Lead has no own name column, so
     * search/order both go through the registry's name (spec 0041 D-1;
     * mirrors OperationalSiteService::forSelect's primary-address subquery
     * pattern). Feeds the Opportunity form's "Lead" select (spec 0040).
     */
    public function forSelect(ForSelectQuery $query): ForSelectResult
    {
        $base = Lead::query()->with(['registry', 'campaign']);

        if ($query->hasSearch()) {
            $term = '%'.$query->search.'%';
            $base->whereHas('registry', function (Builder $registryQuery) use ($term): void {
                $registryQuery->where('name', 'like', $term);
            });
        }

        $total = (clone $base)->count();

        /** @var Collection<int, Lead> $page */
        $page = $base->orderBy($this->registryNameSubquery())
            ->orderBy('id')
            ->offset($query->offset)
            ->limit($query->limit)
            ->get();

        $items = $this->appendHydratedForSelectIds($page, $query);

        return new ForSelectResult(
            items: $items,
            total: $total,
            offset: $query->offset,
            limit: $query->limit,
        );
    }

    /**
     * Correlated subquery selecting the lead's registry name, the ORDER BY
     * key for for-select (a Lead has no own name column). A plain query
     * builder (DB::table), NOT an Eloquent one — `orderBy()` accepts either.
     */
    private function registryNameSubquery(): QueryBuilder
    {
        return DB::table('registries')
            ->select('name')
            ->whereColumn('registries.id', 'leads.registry_id')
            ->limit(1);
    }

    /**
     * Append the explicitly-requested `ids[]` (edit-mode hydration) that are
     * not already on the page, deduplicated. They bypass search and the same
     * relations are eager-loaded. Total is unaffected.
     *
     * @param  Collection<int, Lead>  $page
     * @return Collection<int, Lead>
     */
    private function appendHydratedForSelectIds(Collection $page, ForSelectQuery $query): Collection
    {
        if (! $query->hasIds()) {
            return $page;
        }

        $presentIds = $page->pluck('id')->all();
        $missingIds = array_values(array_diff($query->ids, $presentIds));

        if ($missingIds === []) {
            return $page;
        }

        /** @var Collection<int, Lead> $hydrated */
        $hydrated = Lead::query()
            ->with(['registry', 'campaign'])
            ->whereIn('id', $missingIds)
            ->orderBy($this->registryNameSubquery())
            ->orderBy('id')
            ->get();

        return $page->concat($hydrated);
    }
}

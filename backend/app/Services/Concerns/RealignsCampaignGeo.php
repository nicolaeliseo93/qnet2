<?php

declare(strict_types=1);

namespace App\Services\Concerns;

use App\Models\Campaign;
use App\Models\Project;
use App\Support\Geo\GeoHierarchyMembership;
use Illuminate\Support\Collection;

/**
 * BR-5 realignment cascade (spec 0027 addendum, "the project wins"): a
 * project update that claims or changes a geo level must not leave a linked
 * campaign pointing at a now-incoherent tuple (two independently-valid
 * requests — a campaign refinement, then a later project update — used to be
 * able to strand a campaign on a city that no longer belongs to its merged
 * region). MUST be called from inside the caller's already-open
 * DB::transaction, mirroring GeneratesSequentialCode.
 */
trait RealignsCampaignGeo
{
    /**
     * The 4 geo levels (spec 0027, BR-5), in parent-to-child order — the
     * single source of truth for the cascade below, mirroring
     * CampaignService::GEO_LEVELS.
     *
     * @var array<int, string>
     */
    private const array GEO_LEVELS = ['country_id', 'state_id', 'province_id', 'city_id'];

    /**
     * Every campaign linked to $project is locked and, for each:
     *   Step 1: reclaim every level the project NOW owns — BR-5 wants NULL
     *           there (the campaign goes back to inheriting it).
     *   Step 2: null whatever finer level is still the campaign's OWN but no
     *           longer forms a consistent chain with the resulting merged
     *           tuple (e.g. a city that no longer belongs to the new state).
     * Only campaigns actually left dirty are saved, so `LogsModelActivity`
     * (already on Campaign) records exactly who cleared what and when — this
     * operation destroys data a user chose, so it must be auditable.
     */
    protected function realignLinkedCampaignsGeo(Project $project): void
    {
        /** @var Collection<int, Campaign> $campaigns */
        $campaigns = $project->campaigns()->lockForUpdate()->get();

        foreach ($campaigns as $campaign) {
            $this->reclaimOwnedLevels($campaign, $project);
            $this->clearIncoherentLevels($campaign, $project);

            if ($campaign->isDirty(self::GEO_LEVELS)) {
                $campaign->save();
            }
        }
    }

    /**
     * Step 1: any level the project now fills is prohibited on the campaign
     * (BR-5) — force it back to NULL regardless of what the campaign
     * previously refined there.
     */
    private function reclaimOwnedLevels(Campaign $campaign, Project $project): void
    {
        foreach (self::GEO_LEVELS as $level) {
            if ($project->{$level} !== null) {
                $campaign->{$level} = null;
            }
        }
    }

    /**
     * Step 2: re-check the campaign's OWN remaining levels (state/province/
     * city — country has no parent to violate) against the tuple resulting
     * from Step 1, in parent-to-child order so an ancestor nulled by THIS
     * pass also orphans its descendants. A level still consistent with the
     * new tuple (e.g. a city that still belongs to the project's new region)
     * is left untouched.
     */
    private function clearIncoherentLevels(Campaign $campaign, Project $project): void
    {
        $countryId = $project->country_id ?? $campaign->country_id;

        if ($campaign->state_id !== null && ! GeoHierarchyMembership::stateBelongsToCountry($campaign->state_id, $countryId)) {
            $campaign->state_id = null;
        }

        $stateId = $project->state_id ?? $campaign->state_id;

        if ($campaign->province_id !== null && ! GeoHierarchyMembership::provinceBelongsToState($campaign->province_id, $stateId)) {
            $campaign->province_id = null;
        }

        if ($campaign->city_id === null) {
            return;
        }

        if (! GeoHierarchyMembership::cityBelongsToState($campaign->city_id, $stateId)) {
            $campaign->city_id = null;

            return;
        }

        $provinceId = $project->province_id ?? $campaign->province_id;

        if ($provinceId !== null && ! GeoHierarchyMembership::cityBelongsToProvince($campaign->city_id, $provinceId)) {
            $campaign->city_id = null;
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Opportunities;

use App\DataObjects\Opportunities\LeadOpportunityDefaults;
use App\Models\Address;
use App\Models\Lead;
use Illuminate\Database\Eloquent\Model;

/**
 * BR-1 (spec 0040, spec 0041 D-3): resolves the values an Opportunity
 * inherits from a Lead and its Campaign — the SINGLE derivation point
 * consumed both by the `GET /api/leads/{lead}/opportunity-defaults` prefill
 * endpoint and by the OpportunityService/StoreOpportunityRequest/
 * UpdateOpportunityRequest lock enforcement (BR-2), so the two can never
 * drift apart.
 *
 * `registry_id`/`operational_site_id` come straight off the lead (spec 0041
 * D-3: `registry_id` is now the LEAD's own, no longer the campaign's);
 * `source_id` falls back to the campaign's own source when the lead has
 * none; `business_function_id`/`product_category_id` are the campaign's
 * EFFECTIVE values — read through its linked Project when one exists, else
 * the campaign's own (the exact `project !== null ? project->x :
 * campaign->x` merge CampaignResource already uses for the same 2 columns,
 * spec 0023 BR-2 — no second implementation, this mirrors it). `referent_id`
 * is NOT derived (spec 0041 D-3): it stays a plain field, scoped to the
 * chosen registry (BR-4, spec 0040).
 */
final class LeadOpportunityDefaultsResolver
{
    /**
     * The 5 BR-1-derivable field keys, in the contract's declared order.
     *
     * @var array<int, string>
     */
    private const array DERIVED_FIELDS = [
        'source_id',
        'operational_site_id',
        'registry_id',
        'business_function_id',
        'product_category_id',
    ];

    /**
     * Relations resolve() needs loaded on $lead to stay N+1-free; harmless
     * (loadMissing) when the caller already eager-loaded some or all of them.
     *
     * @var array<int, string>
     */
    private const array REQUIRED_RELATIONS = [
        'registry',
        'operationalSite.addresses.city',
        'source',
        'opportunity',
        'campaign.source',
        'campaign.businessFunction',
        'campaign.productCategory',
        'campaign.project.businessFunction',
        'campaign.project.productCategory',
    ];

    public function resolve(Lead $lead): LeadOpportunityDefaults
    {
        $lead->loadMissing(self::REQUIRED_RELATIONS);

        $campaign = $lead->campaign;
        $project = $campaign->project;

        $effectiveSource = $lead->source_id !== null ? $lead->source : $campaign->source;
        $effectiveBusinessFunction = $project !== null ? $project->businessFunction : $campaign->businessFunction;
        $effectiveProductCategory = $project !== null ? $project->productCategory : $campaign->productCategory;

        $values = [
            'source_id' => $effectiveSource?->id,
            'operational_site_id' => $lead->operational_site_id,
            'registry_id' => $lead->registry_id,
            'business_function_id' => $effectiveBusinessFunction?->id,
            'product_category_id' => $effectiveProductCategory?->id,
        ];

        $references = [
            'source' => $this->summarizeByName($effectiveSource),
            'operational_site' => $this->summarizeOperationalSite($lead->operationalSite),
            'registry' => $this->summarizeByName($lead->registry),
            'business_function' => $this->summarizeByName($effectiveBusinessFunction),
            'product_category' => $this->summarizeByName($effectiveProductCategory),
        ];

        return new LeadOpportunityDefaults(
            values: $values,
            references: $references,
            lockedFields: $this->lockedFields($values),
            existingOpportunityId: $lead->opportunity?->id,
        );
    }

    /**
     * @param  array<string, int|null>  $values
     * @return array<int, string>
     */
    private function lockedFields(array $values): array
    {
        return array_values(array_filter(
            self::DERIVED_FIELDS,
            static fn (string $field): bool => $values[$field] !== null,
        ));
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function summarizeByName(?Model $related): ?array
    {
        return $related === null ? null : ['id' => $related->id, 'name' => $related->name];
    }

    /**
     * `operational_site` has no own name column (mirrors LeadResource's
     * identical composition): label = primary address `line1` plus
     * " - {city}" when present.
     *
     * @return array{id: int, label: string}|null
     */
    private function summarizeOperationalSite(mixed $site): ?array
    {
        if ($site === null) {
            return null;
        }

        /** @var Address|null $address */
        $address = $site->addresses->first();

        return ['id' => $site->id, 'label' => $this->composeSiteLabel($address)];
    }

    private function composeSiteLabel(?Address $address): string
    {
        if ($address === null) {
            return '';
        }

        $city = $address->city?->name;

        return $city === null ? (string) $address->line1 : "{$address->line1} - {$city}";
    }
}

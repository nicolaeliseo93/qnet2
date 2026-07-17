<?php

namespace App\Http\Resources;

use App\Models\Opportunity;
use App\Services\Opportunities\LeadOpportunityDefaultsResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Opportunity
 *
 * `locked_fields` (spec 0040, BR-2) is [] when the opportunity has no lead,
 * else re-resolved from the CURRENT lead/campaign state via
 * LeadOpportunityDefaultsResolver — the same single source of truth the
 * write-path lock enforcement uses. Relies on OpportunityService::loadDetail()
 * having eager-loaded every relation this touches (including the lead's own
 * chain), so resolving any of them here never N+1s.
 *
 * Amendment rev.3: `business_function_id`/`business_function`/
 * `product_category_id`/`product_category` are REPLACED by `product_lines`
 * (one row per funzione-aziendale + categoria-prodotto pair). User directive
 * 2026-07-17: `company_id`/`company`/`company_site_id`/`company_site`/
 * `operational_site_id`/`operational_site` are REMOVED entirely.
 */
class OpportunityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'registry_id' => $this->registry_id,
            'registry' => $this->summarizeByName($this->registry),
            'referent_id' => $this->referent_id,
            'referent' => $this->summarizeByName($this->referent),
            'commercial_id' => $this->commercial_id,
            'commercial' => $this->summarizeByName($this->commercial),
            'reporter_id' => $this->reporter_id,
            'reporter' => $this->summarizeByName($this->reporter),
            'supervisor_id' => $this->supervisor_id,
            'supervisor' => $this->summarizeByName($this->supervisor),
            'source_id' => $this->source_id,
            'source' => $this->summarizeByName($this->source),
            'product_lines' => $this->summarizeProductLines($this->productLines),
            'lead_id' => $this->lead_id,
            'lead' => $this->summarizeLead($this->lead),
            'managers' => $this->summarizeManagers($this->managers),
            'start_date' => $this->start_date,
            'estimated_value' => $this->estimated_value,
            'expected_close_date' => $this->expected_close_date,
            'success_probability' => $this->success_probability,
            'locked_fields' => $this->resolveLockedFields(),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    /**
     * @return array{id: int, name: string}|null
     */
    private function summarizeByName(?Model $related): ?array
    {
        return $related === null ? null : ['id' => $related->id, 'name' => $related->name];
    }

    /**
     * @return array{id: int, label: string}|null
     */
    private function summarizeLead(mixed $lead): ?array
    {
        return $lead === null ? null : ['id' => $lead->id, 'label' => $lead->registry?->name ?? ''];
    }

    /**
     * @return array<int, array{id: int, business_function: array{id: int, name: string}|null, product_category: array{id: int, name: string}|null}>
     */
    private function summarizeProductLines(iterable $lines): array
    {
        return collect($lines)
            ->map(fn (Model $line): array => [
                'id' => $line->id,
                'business_function' => $this->summarizeByName($line->businessFunction),
                'product_category' => $this->summarizeByName($line->productCategory),
            ])
            ->all();
    }

    /**
     * @return array<int, array{id: int, name: string, position: int}>
     */
    private function summarizeManagers(iterable $managers): array
    {
        return collect($managers)
            ->map(fn (Model $manager): array => [
                'id' => $manager->id,
                'name' => $manager->name,
                'position' => (int) $manager->pivot->position,
            ])
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function resolveLockedFields(): array
    {
        if ($this->lead_id === null) {
            return [];
        }

        $lead = $this->lead;

        if ($lead === null) {
            return [];
        }

        return app(LeadOpportunityDefaultsResolver::class)->resolve($lead)->lockedFields;
    }
}

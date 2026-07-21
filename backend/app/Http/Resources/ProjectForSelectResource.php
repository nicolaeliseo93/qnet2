<?php

namespace App\Http\Resources;

use App\Http\Resources\Abstracts\ForSelectResource;
use App\Models\Address;
use App\Models\Project;
use App\Support\Geo\GeoNameLocalizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * For-select projection of a Project (GET /api/projects/for-select, spec
 * 0023). Label is "{code} — {name}"; `meta` carries the campaign-form
 * defaults (partner/pipeline_status/business_function/state/
 * product_category/operational_site, each {id, label} or null) plus the BR-7
 * budget figures, so selecting a project in the Campaign form precompiles it
 * with no extra request (ADR 0011). `operational_site` (prefill-modifiable
 * sede) has no own name column: its label is composed the same way
 * LeadResource/OperationalSiteForSelectResource do.
 *
 * @mixin Project
 */
class ProjectForSelectResource extends ForSelectResource
{
    /**
     * @return array<string, mixed>
     */
    protected function forSelectItem(Request $request): array
    {
        $totalBudget = $this->total_budget;
        $allocatedBudget = (float) ($this->allocated_budget_sum ?? 0);

        return [
            'id' => $this->id,
            'label' => sprintf('%s — %s', $this->code, $this->name),
            'meta' => [
                'partner' => $this->summarize($this->partner),
                'pipeline_status' => $this->summarize($this->pipelineStatus),
                'business_function' => $this->summarize($this->businessFunction),
                'state' => $this->summarize($this->state, geo: true),
                'product_category' => $this->summarize($this->productCategory),
                'operational_site' => $this->summarizeOperationalSite($this->operationalSite),
                'total_budget' => $totalBudget === null ? null : $this->formatMoney((float) $totalBudget),
                'allocated_budget' => $this->formatMoney($allocatedBudget),
                'remaining_budget' => $totalBudget === null ? null : $this->formatMoney((float) $totalBudget - $allocatedBudget),
                // spec 0027, D-5: which geo levels this project already fills,
                // so the Campaign form knows which to lock/prefill.
                'geo' => [
                    'country' => $this->summarizeGeo($this->country),
                    'state' => $this->summarizeGeo($this->state),
                    'province' => $this->summarizeGeo($this->province),
                    'city' => $this->summarizeGeo($this->city),
                ],
            ],
        ];
    }

    /**
     * A related row projected to {id, label}. `$geo` localizes the label to
     * Italian (state only here) — never applied to the other relations, whose
     * names are user data.
     *
     * @return array{id: int, label: string}|null
     */
    private function summarize(?Model $related, bool $geo = false): ?array
    {
        if ($related === null) {
            return null;
        }

        $label = $geo ? GeoNameLocalizer::toItalian($related->name) : $related->name;

        return ['id' => $related->id, 'label' => $label];
    }

    /**
     * `meta.geo.*` shape (spec 0027, D-5): `{id, name}`, distinct from the
     * rest of `meta`'s `{id, label}` — this block feeds GeoSelect directly,
     * which already speaks `name`.
     *
     * @return array{id: int, name: string}|null
     */
    private function summarizeGeo(?Model $related): ?array
    {
        if ($related === null) {
            return null;
        }

        return ['id' => $related->id, 'name' => $related->name];
    }

    private function formatMoney(float $value): string
    {
        return number_format($value, 2, '.', '');
    }

    /**
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

        $city = $address->city?->localizedName();

        return $city === null ? (string) $address->line1 : "{$address->line1} - {$city}";
    }
}

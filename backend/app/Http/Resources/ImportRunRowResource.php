<?php

namespace App\Http\Resources;

use App\Models\ImportRunRow;
use App\Models\OperationalSite;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ImportRunRow
 *
 * The frozen `row` shape (spec 0033 data_contract — POST .../rows SSRM review
 * and PATCH .../rows/{row}): `values` merges the mapped field values with the
 * `__extra__` ones (both already keyed the way the review grid/edit form
 * expect — field id for mapped, original column name for extra), so the
 * frontend never has to know which store a given key came from. Never the
 * raw `import_run_id`/timestamps — those are server-only bookkeeping.
 * `duplicate_meta`/`resolution` (spec 0036, additive) are null on any row
 * that never matched an existing record. `operator_id`/`operator` and
 * `operational_site_id`/`operational_site` (spec 0045, additive) carry the
 * per-row Operator/Operational Site overrides — null on a row that still
 * defers to the run's global value.
 */
class ImportRunRowResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ImportRunRow $row */
        $row = $this->resource;

        return [
            'id' => $row->id,
            'row_number' => $row->row_number,
            'status' => $row->status->value,
            'is_edited' => $row->is_edited,
            'duplicate_of_id' => $row->duplicate_of_id,
            'duplicate_meta' => $row->duplicate_meta,
            'resolution' => $row->resolution?->value,
            'operator_id' => $row->operator_id,
            'operator' => $row->operator === null ? null : ['id' => $row->operator->id, 'name' => $row->operator->name],
            'operational_site_id' => $row->operational_site_id,
            'operational_site' => $row->operationalSite === null ? null : ['id' => $row->operationalSite->id, 'name' => $this->siteLabel($row->operationalSite)],
            'values' => [...($row->mapped_values ?? []), ...($row->extra_values ?? [])],
            'messages' => $row->messages ?? [],
        ];
    }

    /**
     * OperationalSite has no own `name` column (identity = its address, see
     * App\Models\OperationalSite) — mirrors OperationalSiteForSelectResource's
     * label composition: primary address line1, plus " - {city}" when the
     * address has a city, falling back to `alias` when the site has no
     * address at all.
     */
    private function siteLabel(OperationalSite $site): string
    {
        $address = $site->addresses->firstWhere('is_primary', true) ?? $site->addresses->first();

        if ($address === null) {
            return (string) $site->alias;
        }

        $city = $address->city?->localizedName();

        return $city === null ? (string) $address->line1 : "{$address->line1} - {$city}";
    }
}

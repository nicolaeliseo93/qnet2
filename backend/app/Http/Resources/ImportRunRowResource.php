<?php

namespace App\Http\Resources;

use App\Models\ImportRunRow;
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
            'values' => [...($row->mapped_values ?? []), ...($row->extra_values ?? [])],
            'messages' => $row->messages ?? [],
        ];
    }
}

<?php

namespace App\Http\Resources;

use App\Models\Registry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Registry
 */
class RegistryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'source_id' => $this->source_id,
            'source' => $this->toRef($this->source),
            'sector_ids' => $this->sectors->pluck('id')->all(),
            'sectors' => $this->toRefList($this->sectors),
            'referent_ids' => $this->referents->pluck('id')->all(),
            'referents' => $this->toRefList($this->referents),
            'manager_ids' => $this->managers->pluck('id')->all(),
            'managers' => $this->toRefList($this->managers),
            'supervisor_id' => $this->supervisor_id,
            'supervisor' => $this->toRef($this->supervisor),
            'commercial_id' => $this->commercial_id,
            'commercial' => $this->toRef($this->commercial),
            'reporter_id' => $this->reporter_id,
            'reporter' => $this->toRef($this->reporter),
            'vat_group' => $this->vat_group,
            'is_supplier' => $this->is_supplier,
            'is_qualified_supplier' => $this->is_qualified_supplier,
            'agreement_status' => $this->agreement_status,
            'agreement_notes' => $this->agreement_notes,
            'size_class' => $this->size_class,
            'employee_count' => $this->employee_count,
            // The nested personal-data tree, or null — always present as a
            // key (the Service always eager-loads
            // `personalData.contacts`/`personalData.addresses`).
            'personal_data' => $this->personalData !== null
                ? new PersonalDataResource($this->personalData)
                : null,
            'created_at' => $this->created_at,
        ];
    }

    /**
     * A single to-one relation projected to {id, name}, or null — the
     * Service always eager-loads these, so this never triggers a lazy load.
     *
     * @return array{id: int, name: string}|null
     */
    private function toRef(mixed $related): ?array
    {
        return $related !== null ? ['id' => $related->id, 'name' => $related->name] : null;
    }

    /**
     * A to-many relation projected to a list of {id, name}.
     *
     * @return array<int, array{id: int, name: string}>
     */
    private function toRefList(iterable $related): array
    {
        return collect($related)->map(fn ($item): array => ['id' => $item->id, 'name' => $item->name])->all();
    }
}

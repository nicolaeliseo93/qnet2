<?php

namespace App\Http\Resources;

use App\Models\Contact;
use App\Models\PersonalData;
use App\Models\Registry;
use App\Tables\Shared\PrimaryContactColumn;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

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
            'supervisor' => $this->toPersonRef($this->supervisor),
            'commercial_id' => $this->commercial_id,
            'commercial' => $this->toPersonRef($this->commercial),
            'reporter_id' => $this->reporter_id,
            'reporter' => $this->toPersonRef($this->reporter),
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
     * A responsible person (supervisor/commercial/reporter — a User or a
     * Referent, both `HasPersonalData`) projected to {id, name, primary_contacts}.
     * The service eager-loads `<rel>.personalData.contacts`, so surfacing each
     * person's PRIMARY contacts (one per type) beside the name never lazy-loads.
     *
     * @return array{id: int, name: string, primary_contacts: array<int, array{type: string, icon: string|null, label: string, value: string}>}|null
     */
    private function toPersonRef(?Model $related): ?array
    {
        if ($related === null) {
            return null;
        }

        return [
            'id' => $related->id,
            'name' => $related->name,
            'primary_contacts' => (new PrimaryContactColumn)->format($this->primaryContactsOf($related)),
        ];
    }

    /**
     * The person's primary contacts (is_primary), or null when they have no
     * card. Reads from the eager-loaded relation only.
     *
     * @return Collection<int, Contact>|null
     */
    private function primaryContactsOf(Model $person): ?Collection
    {
        /** @var PersonalData|null $card */
        $card = $person->personalData;

        return $card !== null
            ? $card->contacts->where('is_primary', true)->values()
            : null;
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

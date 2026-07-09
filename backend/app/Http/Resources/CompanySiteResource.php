<?php

namespace App\Http\Resources;

use App\Models\CompanySite;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CompanySite
 *
 * Full company-site shape (spec 0020): the site's own fields plus its nested
 * personal-data card (contacts + address) via PersonalDataResource — exactly
 * like RegistryResource — the owned banks and the Impostazioni fields. The
 * former "Altro" section is gone: those attributes are now universal custom
 * fields (spec 0021), serialized generically via the custom-fields decorator.
 * The 4 `responsible_*` relations are emitted as {id,label} references
 * (mirrors EmploymentResource::reference) only when eager-loaded
 * (CompanySiteService::loadTree always does, so no N+1 in practice).
 */
class CompanySiteResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return array_merge($this->coreFields(), $this->settingsFields());
    }

    /**
     * @return array<string, mixed>
     */
    private function coreFields(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'notes' => $this->notes,
            'is_default' => $this->is_default,
            'logo_url' => $this->logoDataUri(),
            // The nested personal-data tree (card + contacts + address), or
            // null — always present as a key (the Service always eager-loads
            // `personalData.contacts`/`personalData.addresses`), mirrors
            // RegistryResource.
            'personal_data' => $this->personalData !== null
                ? new PersonalDataResource($this->personalData)
                : null,
            'banks' => CompanySiteBankResource::collection($this->whenLoaded('banks')),
            'created_at' => $this->created_at,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function settingsFields(): array
    {
        return [
            'company_id' => $this->company_id,
            'company' => $this->when(
                $this->relationLoaded('company') && $this->company !== null,
                fn (): array => ['id' => $this->company->id, 'label' => $this->company->denomination],
            ),
            'responsible_rda_id' => $this->responsible_rda_id,
            'responsible_rda' => $this->when(
                $this->relationLoaded('responsibleRda') && $this->responsibleRda !== null,
                fn (): array => $this->reference($this->responsibleRda),
            ),
            'responsible_tickets_id' => $this->responsible_tickets_id,
            'responsible_tickets' => $this->when(
                $this->relationLoaded('responsibleTickets') && $this->responsibleTickets !== null,
                fn (): array => $this->reference($this->responsibleTickets),
            ),
            'responsible_validation_contracts_id' => $this->responsible_validation_contracts_id,
            'responsible_validation_contracts' => $this->when(
                $this->relationLoaded('responsibleValidationContracts') && $this->responsibleValidationContracts !== null,
                fn (): array => $this->reference($this->responsibleValidationContracts),
            ),
            'responsible_validation_contracts_two_id' => $this->responsible_validation_contracts_two_id,
            'responsible_validation_contracts_two' => $this->when(
                $this->relationLoaded('responsibleValidationContractsTwo') && $this->responsibleValidationContractsTwo !== null,
                fn (): array => $this->reference($this->responsibleValidationContractsTwo),
            ),
            'proforma_progressive' => $this->proforma_progressive,
            'invoice_progressive' => $this->invoice_progressive,
            'quotation_layout_id' => $this->quotation_layout_id,
            'quotation_header_id' => $this->quotation_header_id,
            'quotation_footer_id' => $this->quotation_footer_id,
        ];
    }

    /**
     * @return array{id: int, label: string}
     */
    private function reference(User $user): array
    {
        return ['id' => $user->id, 'label' => $user->name];
    }
}

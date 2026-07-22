<?php

namespace App\Http\Resources;

use App\Models\Opportunity;
use App\Models\OpportunityWorkflowStatus;
use App\RequestManagement\ApplicableAttribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;

/**
 * Wire shape for the request-management work panel (spec 0049,
 * data_contract GET/PATCH /api/request-management/{opportunity}). Consumes
 * the {opportunity, applicable_attributes, workflow_statuses} array
 * RequestManagementService::loadWorkPanel()/updateWork() build — never a raw
 * Opportunity/OpportunityResource: this is a DEDICATED, purpose-built shape
 * for the operative panel (contacts owners, applicable_attributes,
 * read-only context), independent from the opportunities CRUD resource
 * (D-1/constraints: no change to OpportunityResource's own contract here).
 *
 * `client_contacts`/`referent_contacts` expose an `owner` OwnerRef
 * (`{type: 'personal_data', id}`) alongside the contact `items`, so the
 * frontend's ContactsManager can persist directly against the PersonalData
 * card (D-6) without re-deriving the owner from the opportunity. Registry/
 * Referent are NOT valid `contactable_type`s (`config/personal_data.php`
 * `contactable_types` lists only `personal_data`), so the ref must point at
 * the PersonalData card, never the entity.
 */
class RequestManagementResource extends JsonResource
{
    /**
     * @param  array{opportunity: Opportunity, applicable_attributes: Collection<int, ApplicableAttribute>, workflow_statuses: Collection<int, OpportunityWorkflowStatus>}  $resource
     */
    public function __construct(array $resource)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Opportunity $opportunity */
        $opportunity = $this->resource['opportunity'];

        return [
            'id' => $opportunity->id,
            'name' => $opportunity->name,
            'registry' => $this->summarizeByName($opportunity->registry),
            'referent' => $this->summarizeByName($opportunity->referent),
            'commercial' => $this->summarizeByName($opportunity->commercial),
            'opportunity_status' => $this->summarizeStatus($opportunity->opportunityStatus),
            'workflow_status' => $this->summarizeWorkflowStatus($opportunity->workflowStatus),
            'workflow_statuses' => $this->summarizeWorkflowStatuses($this->resource['workflow_statuses']),
            'product_lines' => $this->summarizeProductLines($opportunity->productLines),
            'client_contacts' => $this->summarizeContacts($opportunity->registry),
            'referent_contacts' => $this->summarizeContacts($opportunity->referent),
            'applicable_attributes' => $this->summarizeApplicableAttributes($this->resource['applicable_attributes']),
            'attribute_values' => $opportunity->attribute_values ?? [],
            'context' => [
                'estimated_value' => $opportunity->estimated_value,
                'expected_close_date' => $opportunity->expected_close_date?->format('Y-m-d'),
                'success_probability' => $opportunity->success_probability,
            ],
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
     * `opportunity_status` (pipeline, read-only in this module — D-5).
     *
     * @return array{id: int, name: string, color: string|null}|null
     */
    private function summarizeStatus(?Model $status): ?array
    {
        return $status === null ? null : ['id' => $status->id, 'name' => $status->name, 'color' => $status->color];
    }

    /**
     * @return array{id: int, name: string, description: string|null, color: string|null, system_key: string|null, requires_note: bool}|null
     */
    private function summarizeWorkflowStatus(?OpportunityWorkflowStatus $status): ?array
    {
        return $status === null ? null : [
            'id' => $status->id,
            'name' => $status->name,
            'description' => $status->description,
            'color' => $status->color,
            'system_key' => $status->system_key,
            'requires_note' => $status->requires_note,
        ];
    }

    /**
     * @param  Collection<int, OpportunityWorkflowStatus>  $statuses
     * @return array<int, array{id: int, name: string, description: string|null, color: string|null, system_key: string|null, requires_note: bool}>
     */
    private function summarizeWorkflowStatuses(Collection $statuses): array
    {
        return $statuses
            ->map(fn (OpportunityWorkflowStatus $status): array => $this->summarizeWorkflowStatus($status))
            ->values()
            ->all();
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
     * The `client_contacts`/`referent_contacts` block (D-6): the OwnerRef the
     * frontend's ContactsManager persists against, plus the owner's current
     * contact channels via PersonalData (HasPersonalData -> HasContacts).
     * `owner` MUST reference the PersonalData card itself — Registry/Referent
     * are not valid `contactable_type`s (`config/personal_data.php`
     * `contactable_types` => ['personal_data' => PersonalData]) — so
     * ContactsManager's create/update writes land on the right
     * `contactable_type`/`contactable_id`. `null` when the entity has no
     * PersonalData card.
     *
     * @return array{owner: array{type: 'personal_data', id: int}|null, items: AnonymousResourceCollection|array<int, mixed>}
     */
    private function summarizeContacts(?Model $entity): array
    {
        $personalData = $entity?->personalData;

        if ($personalData === null) {
            return ['owner' => null, 'items' => []];
        }

        return [
            'owner' => ['type' => 'personal_data', 'id' => $personalData->id],
            'items' => ContactResource::collection($personalData->contacts),
        ];
    }

    /**
     * @param  Collection<int, ApplicableAttribute>  $attributes
     * @return array<int, array<string, mixed>>
     */
    private function summarizeApplicableAttributes(Collection $attributes): array
    {
        return $attributes
            ->map(fn (ApplicableAttribute $attribute): array => $attribute->toArray())
            ->values()
            ->all();
    }
}

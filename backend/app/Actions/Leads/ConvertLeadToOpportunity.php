<?php

declare(strict_types=1);

namespace App\Actions\Leads;

use App\DataObjects\Opportunities\CreateOpportunityData;
use App\Models\Lead;
use App\Models\Opportunity;
use App\Models\OpportunityStatus;
use App\Services\Opportunities\LeadOpportunityDefaultsResolver;
use App\Services\OpportunityService;
use App\Services\Statuses\SystemStatusGuard;
use Illuminate\Validation\ValidationException;

/**
 * Contextual Lead -> Opportunity conversion (spec 0044): given a just-created
 * Lead, derives and persists its linked Opportunity. Called by
 * LeadService::create() from WITHIN the same DB::transaction it already
 * opened around the Lead insert, so a failure here (e.g. AC-012, empty
 * product lines) rolls the Lead back too — this class does not open its own
 * outer transaction.
 *
 * Reuses LeadOpportunityDefaultsResolver (the single BR-1 derivation point,
 * spec 0040) for registry_id/source_id/product lines, and
 * OpportunityService::create() for the actual persistence (product lines
 * sync), rather than re-implementing either.
 */
final class ConvertLeadToOpportunity
{
    /**
     * Joins the derived product categories' names into the opportunity's
     * auto-computed name (spec 0044's internal_contract), mirroring the
     * frontend's PRODUCT_LINE_NAME_SEPARATOR
     * (opportunity-product-line-name.ts).
     */
    private const string PRODUCT_LINE_NAME_SEPARATOR = ' + ';

    public function __construct(
        private readonly LeadOpportunityDefaultsResolver $defaultsResolver,
        private readonly SystemStatusGuard $systemStatusGuard,
        private readonly OpportunityService $opportunityService,
    ) {}

    public function handle(Lead $lead): Opportunity
    {
        // Step 1: derive the BR-1 values (registry_id/source_id) and the
        // campaign/project's product line from the lead.
        $defaults = $this->defaultsResolver->resolve($lead);

        // Step 2: a campaign/project with no business function or product
        // category derives nothing to seed the opportunity's mandatory
        // product line with (AC-012) — reject before persisting anything.
        if ($defaults->productLines === []) {
            throw ValidationException::withMessages([
                'product_lines' => ["The lead's campaign has no business function or product category to derive an opportunity from."],
            ]);
        }

        // Step 3: persist through OpportunityService::create(), so product
        // line sync stays the single implementation.
        return $this->opportunityService->create(new CreateOpportunityData(
            name: $this->composeName($defaults->productLines),
            registryId: $defaults->values['registry_id'],
            referentId: null,
            commercialId: null,
            reporterId: null,
            supervisorId: $lead->operator_id,
            sourceId: $defaults->values['source_id'],
            leadId: $lead->id,
            opportunityStatusId: $this->systemStatusGuard->resolveNewStatusId(OpportunityStatus::class),
            managerSlots: null,
            productLines: $this->toProductLineAttributes($defaults->productLines),
            startDate: null,
            estimatedValue: null,
            expectedCloseDate: null,
            successProbability: null,
        ));
    }

    /**
     * @param  array<int, array{business_function: array{id: int, name: string}, product_category: array{id: int, name: string}}>  $productLines
     */
    private function composeName(array $productLines): string
    {
        return implode(
            self::PRODUCT_LINE_NAME_SEPARATOR,
            array_map(static fn (array $line): string => $line['product_category']['name'], $productLines),
        );
    }

    /**
     * @param  array<int, array{business_function: array{id: int, name: string}, product_category: array{id: int, name: string}}>  $productLines
     * @return array<int, array{business_function_id: int, product_category_id: int}>
     */
    private function toProductLineAttributes(array $productLines): array
    {
        return array_map(static fn (array $line): array => [
            'business_function_id' => $line['business_function']['id'],
            'product_category_id' => $line['product_category']['id'],
        ], $productLines);
    }
}

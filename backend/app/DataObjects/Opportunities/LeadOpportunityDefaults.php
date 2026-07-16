<?php

declare(strict_types=1);

namespace App\DataObjects\Opportunities;

/**
 * The BR-1-derived values for an Opportunity generated from a Lead (spec
 * 0040), computed by LeadOpportunityDefaultsResolver: the single source of
 * truth consumed BOTH by `GET /api/leads/{lead}/opportunity-defaults` (the
 * create-form prefill) and by the store/update enforcement (BR-2 lock).
 */
final readonly class LeadOpportunityDefaults
{
    /**
     * @param  array<string, int|null>  $values  keyed by the 6 derivable fields (referent_id/source_id/operational_site_id/registry_id/business_function_id/product_category_id)
     * @param  array<string, array{id: int, name: string}|array{id: int, label: string}|null>  $references  same keys, {id,name|label} summaries
     * @param  array<int, string>  $lockedFields  the subset of $values whose derivation is non-null (BR-2)
     */
    public function __construct(
        public array $values,
        public array $references,
        public array $lockedFields,
        public ?int $existingOpportunityId,
    ) {}
}

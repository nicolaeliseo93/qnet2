<?php

declare(strict_types=1);

namespace App\DataObjects\Opportunities;

/**
 * The BR-1-derived values for an Opportunity generated from a Lead (spec
 * 0040), computed by LeadOpportunityDefaultsResolver: the single source of
 * truth consumed BOTH by `GET /api/leads/{lead}/opportunity-defaults` (the
 * create-form prefill) and by the store/update enforcement (BR-2 lock).
 *
 * Amendment rev.3: `business_function_id`/`product_category_id` are REMOVED
 * from `values`/`references`/`lockedFields` (no longer derivable/lockable —
 * the row they used to populate is now EDITABLE/removable in the form, never
 * BR-2-locked); `productLines` carries the 0-or-1 row derived from the
 * lead/campaign's EFFECTIVE business function + product category, when BOTH
 * are present.
 *
 * User directive 2026-07-21: the lead's Operator no longer prefills the
 * Opportunity's Supervisor — it seeds the FIRST "Gestore Account" slot
 * instead (`managerSlots`/`managerRefs`), and the Supervisor stays empty.
 * A 0-or-1-element list (the lead has at most one Operator, AC-025), never
 * locked.
 */
final readonly class LeadOpportunityDefaults
{
    /**
     * @param  array<string, int|null>  $values  keyed by the 2 derivable fields (source_id/registry_id)
     * @param  array<string, array{id: int, name: string}|null>  $references  same keys, {id,name} summaries
     * @param  array<int, string>  $lockedFields  the subset of $values whose derivation is non-null (BR-2)
     * @param  array<int, array{business_function: array{id: int, name: string}, product_category: array{id: int, name: string}}>  $productLines  0 or 1 row, editable/removable in the form (never locked)
     * @param  array<int, int>  $managerSlots  0 or 1 slot: the lead's Operator id, prefilling the first "Gestore Account" (never locked)
     * @param  array<int, array{id: int, name: string}>  $managerRefs  {id,name} summaries paired with $managerSlots, for the slot's trigger-label hydration
     */
    public function __construct(
        public array $values,
        public array $references,
        public array $lockedFields,
        public array $productLines,
        public ?int $existingOpportunityId,
        public array $managerSlots,
        public array $managerRefs,
    ) {}
}

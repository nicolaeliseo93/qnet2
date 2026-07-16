<?php

namespace App\Imports\Leads;

/**
 * The rich outcome of `LeadDuplicateMatcher::match()` (spec 0036): the
 * matched Referent's id/display name plus every channel (email/phone/
 * mobile/tax_code) that ALSO matches it, cumulative when more than one
 * applies — backs both `LeadsImportDefinition::resolveDuplicate()` (the
 * legacy id) and `resolveDuplicateMatch()`'s `import_run_rows.duplicate_meta`.
 */
final readonly class LeadDuplicateMatch
{
    /**
     * @param  array<int, string>  $matchedOn  subset of ["email","phone","mobile","tax_code"], in that order
     */
    public function __construct(
        public int $referentId,
        public string $referentName,
        public array $matchedOn,
    ) {}
}

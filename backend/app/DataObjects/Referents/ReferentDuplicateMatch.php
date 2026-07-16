<?php

namespace App\DataObjects\Referents;

/**
 * A single EXISTING referent colliding with the criteria checked by
 * ReferentDuplicateFinder (spec 0037): id, display name and every channel
 * that matches, cumulative. Intentionally carries no contact value/tax_code
 * — the response must never leak another referent's PII (AC-005).
 */
final readonly class ReferentDuplicateMatch
{
    /**
     * @param  array<int, string>  $matchedOn  subset of ["email","phone","mobile","tax_code"], in that order
     */
    public function __construct(
        public int $referentId,
        public string $name,
        public array $matchedOn,
    ) {}
}

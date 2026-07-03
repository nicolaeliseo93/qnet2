<?php

namespace App\Migrations\Support;

/**
 * Outcome of MigrationGeoResolver::resolve (spec 0013 Increment 2): the geo
 * ids resolved from external NAMES (country/region/province/city), each
 * independently nullable, plus the non-fatal warnings for whichever level
 * could not be resolved. The caller (CompaniesSource/OperationalSitesSource)
 * builds the address with whatever resolved, appending the warnings to the
 * row's outcome — never a fatal error.
 */
final readonly class MigrationGeoResolution
{
    /**
     * @param  array<int, string>  $warnings
     */
    public function __construct(
        public ?int $countryId,
        public ?int $stateId,
        public ?int $provinceId,
        public ?int $cityId,
        public array $warnings,
    ) {}
}

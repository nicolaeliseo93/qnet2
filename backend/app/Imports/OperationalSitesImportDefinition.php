<?php

namespace App\Imports;

use App\DataObjects\OperationalSites\CreateOperationalSiteData;
use App\Imports\Support\GeoResolutionResult;
use App\Imports\Support\GeoResolver;
use App\Models\OperationalSite;
use App\Models\User;
use App\Services\OperationalSiteService;

/**
 * Import definition for `operational-sites` (spec 0012 AC-014).
 *
 * Columns: `country`/`region`/`province`/`postal_code` (optional),
 * `city`/`street` (required) — an operational site IS its address (no other
 * own field, mirrors CreateOperationalSiteData's flat shape, unlike
 * companies' nested `address` object). Geo NAMES are resolved to ids via
 * GeoResolver.
 *
 * NO database natural key (existsInDatabase always false): two sites at the
 * SAME city+street are legitimately possible in the domain, so dedup here is
 * INTRA-FILE ONLY, keyed on the normalized city+street pair.
 *
 * Row creation delegates entirely to OperationalSiteService::create() (which
 * itself delegates to AddressService) — no duplicated logic.
 */
class OperationalSitesImportDefinition extends AbstractImportDefinition
{
    public function __construct(
        private readonly OperationalSiteService $service,
        private readonly GeoResolver $geoResolver,
    ) {}

    public function domain(): string
    {
        return 'operational-sites';
    }

    public function modelClass(): string
    {
        return OperationalSite::class;
    }

    public function columns(): array
    {
        return [
            ['id' => 'country', 'required' => false],
            ['id' => 'region', 'required' => false],
            ['id' => 'province', 'required' => false],
            ['id' => 'city', 'required' => true],
            ['id' => 'street', 'required' => true],
            ['id' => 'postal_code', 'required' => false],
        ];
    }

    public function validateRow(array $row, ImportRowContext $context): array
    {
        $errors = [];

        if (trim($row['city'] ?? '') === '') {
            $errors[] = 'city is required.';
        }

        if (trim($row['street'] ?? '') === '') {
            $errors[] = 'street is required.';
        }

        $geo = $this->resolveGeo($row);

        if (! $geo->isResolved()) {
            $errors[] = $geo->error;
        }

        return $errors;
    }

    /**
     * No DB natural key for this resource: dedup on the normalized
     * city+street pair, intra-file only (see existsInDatabase()).
     */
    public function dedupKey(array $row): ?string
    {
        $city = trim($row['city'] ?? '');
        $street = trim($row['street'] ?? '');

        return $city === '' || $street === '' ? null : mb_strtolower($city).'|'.mb_strtolower($street);
    }

    public function existsInDatabase(string $key): bool
    {
        return false;
    }

    public function createRow(User $actor, array $row): void
    {
        $geo = $this->resolveGeo($row);

        $this->service->create($actor, new CreateOperationalSiteData(
            line1: trim($row['street'] ?? ''),
            postalCode: $this->blankToNull($row['postal_code'] ?? null),
            countryId: $geo->countryId,
            stateId: $geo->stateId,
            provinceId: $geo->provinceId,
            cityId: $geo->cityId,
        ));
    }

    private function resolveGeo(array $row): GeoResolutionResult
    {
        return $this->geoResolver->resolve(
            $this->blankToNull($row['country'] ?? null),
            $this->blankToNull($row['region'] ?? null),
            $this->blankToNull($row['province'] ?? null),
            $this->blankToNull($row['city'] ?? null),
        );
    }

    private function blankToNull(?string $value): ?string
    {
        return $value === null || trim($value) === '' ? null : $value;
    }
}

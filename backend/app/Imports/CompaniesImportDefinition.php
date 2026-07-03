<?php

namespace App\Imports;

use App\DataObjects\Companies\CreateCompanyData;
use App\DataObjects\PersonalData\CreateAddress;
use App\Imports\Support\GeoResolutionResult;
use App\Imports\Support\GeoResolver;
use App\Models\Company;
use App\Models\User;
use App\Services\CompanyService;

/**
 * Import definition for `companies` (spec 0012 AC-013).
 *
 * Columns: `denomination` (required, natural key) + `vat_number` + the
 * address block (`country`/`region`/`province`/`city`/`street`/`postal_code`,
 * geo NAMES resolved to ids via GeoResolver — mirrors
 * App\DataObjects\PersonalData\CreateAddress's country_id/state_id/
 * province_id/city_id + line1/postal_code). Address is entirely OPTIONAL
 * (StoreCompanyRequest's own `address.line1 required_with:address` rule is
 * mirrored here: if ANY address column is filled, `street` becomes required).
 *
 * Row creation delegates entirely to CompanyService::create() (which itself
 * delegates the single-primary-address invariant to AddressService) — no
 * duplicated logic.
 */
class CompaniesImportDefinition extends AbstractImportDefinition
{
    public function __construct(
        private readonly CompanyService $service,
        private readonly GeoResolver $geoResolver,
    ) {}

    public function domain(): string
    {
        return 'companies';
    }

    public function modelClass(): string
    {
        return Company::class;
    }

    public function columns(): array
    {
        return [
            ['id' => 'denomination', 'required' => true],
            ['id' => 'vat_number', 'required' => false],
            ['id' => 'country', 'required' => false],
            ['id' => 'region', 'required' => false],
            ['id' => 'province', 'required' => false],
            ['id' => 'city', 'required' => false],
            ['id' => 'street', 'required' => false],
            ['id' => 'postal_code', 'required' => false],
        ];
    }

    public function validateRow(array $row, ImportRowContext $context): array
    {
        $errors = [];

        if (trim($row['denomination'] ?? '') === '') {
            $errors[] = 'denomination is required.';
        }

        if ($this->hasAnyAddressInput($row) && trim($row['street'] ?? '') === '') {
            $errors[] = 'street is required when a country/region/province/city/postal_code is provided.';
        }

        $geo = $this->resolveGeo($row);

        if (! $geo->isResolved()) {
            $errors[] = $geo->error;
        }

        return $errors;
    }

    public function dedupKey(array $row): ?string
    {
        $denomination = trim($row['denomination'] ?? '');

        return $denomination === '' ? null : mb_strtolower($denomination);
    }

    /**
     * Fetches only the `denomination` column and compares in PHP (no raw
     * SQL), same trade-off as GeoResolver.
     */
    public function existsInDatabase(string $key): bool
    {
        return Company::query()
            ->get(['denomination'])
            ->contains(static fn (Company $company): bool => mb_strtolower($company->denomination) === $key);
    }

    public function createRow(User $actor, array $row): void
    {
        $this->service->create($actor, new CreateCompanyData(
            denomination: $row['denomination'],
            vatNumber: $this->blankToNull($row['vat_number'] ?? null),
            address: $this->buildAddress($row),
        ));
    }

    private function buildAddress(array $row): ?CreateAddress
    {
        if (! $this->hasAnyAddressInput($row)) {
            return null;
        }

        $geo = $this->resolveGeo($row);

        return new CreateAddress(
            line1: trim($row['street'] ?? ''),
            postalCode: $this->blankToNull($row['postal_code'] ?? null),
            cityId: $geo->cityId,
            provinceId: $geo->provinceId,
            stateId: $geo->stateId,
            countryId: $geo->countryId,
        );
    }

    /**
     * @param  array<string, string>  $row
     */
    private function hasAnyAddressInput(array $row): bool
    {
        foreach (['country', 'region', 'province', 'city', 'street', 'postal_code'] as $column) {
            if (trim($row[$column] ?? '') !== '') {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, string>  $row
     */
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

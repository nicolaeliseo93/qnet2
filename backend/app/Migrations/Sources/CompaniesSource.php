<?php

namespace App\Migrations\Sources;

use App\DataObjects\Companies\CreateCompanyData;
use App\DataObjects\PersonalData\CreateAddress;
use App\Migrations\AbstractMigrationSource;
use App\Migrations\MigrationImportContext;
use App\Migrations\MigrationRowOutcome;
use App\Migrations\Support\ExternalApiClient;
use App\Migrations\Support\MigrationGeoResolver;
use App\Models\Company;
use App\Services\CompanyService;
use RuntimeException;

/**
 * `companies` migration source (spec 0013 Increment 2): creates the company
 * (denomination [+ vat_number]) via CompanyService, which also creates the
 * single primary address when one is supplied. External geo fields are
 * NAMES (country/region/province/city) resolved to ids via
 * MigrationGeoResolver; an unresolved level is a non-fatal warning — the
 * company (and its address, with whatever geo did resolve) is still
 * created. No `street` at all means no address is attempted (a company may
 * legitimately have none, mirroring CreateCompanyData::hasAddress()).
 */
class CompaniesSource extends AbstractMigrationSource
{
    public function __construct(
        ExternalApiClient $client,
        private readonly CompanyService $service,
        private readonly MigrationGeoResolver $geoResolver,
    ) {
        parent::__construct($client);
    }

    public function key(): string
    {
        return 'companies';
    }

    public function label(): string
    {
        return 'Companies';
    }

    /**
     * @return array<int, array{id: string, label: string, type: string}>
     */
    public function columns(): array
    {
        return [
            ['id' => 'id', 'label' => 'ID', 'type' => 'number'],
            ['id' => 'denomination', 'label' => 'Denomination', 'type' => 'string'],
            ['id' => 'vat_number', 'label' => 'VAT number', 'type' => 'string'],
            ['id' => 'country', 'label' => 'Country', 'type' => 'string'],
            ['id' => 'region', 'label' => 'Region', 'type' => 'string'],
            ['id' => 'province', 'label' => 'Province', 'type' => 'string'],
            ['id' => 'city', 'label' => 'City', 'type' => 'string'],
            ['id' => 'street', 'label' => 'Street', 'type' => 'string'],
            ['id' => 'postal_code', 'label' => 'Postal code', 'type' => 'string'],
        ];
    }

    protected function endpoint(): string
    {
        return 'companies';
    }

    protected function externalId(array $record): int|string|null
    {
        return $record['id'] ?? null;
    }

    /**
     * @param  array<string, mixed>  $record
     * @return array<string, string|int|bool|null>
     */
    protected function mapRow(array $record): array
    {
        return [
            'id' => $record['id'] ?? null,
            'denomination' => $record['denomination'] ?? null,
            'vat_number' => $record['vat_number'] ?? null,
            'country' => $record['country'] ?? null,
            'region' => $record['region'] ?? null,
            'province' => $record['province'] ?? null,
            'city' => $record['city'] ?? null,
            'street' => $record['street'] ?? null,
            'postal_code' => $record['postal_code'] ?? null,
        ];
    }

    protected function processRow(MigrationImportContext $context, array $record): MigrationRowOutcome
    {
        $externalId = $this->externalId($record);

        if ($externalId === null) {
            throw new RuntimeException('External id is required.');
        }

        if ($this->existsByOldId(Company::class, $externalId)) {
            return MigrationRowOutcome::skipped();
        }

        $denomination = trim((string) ($record['denomination'] ?? ''));

        if ($denomination === '') {
            throw new RuntimeException('denomination is required.');
        }

        [$address, $warnings] = $this->buildAddress($record);

        $company = $this->service->create(
            $context->actor,
            new CreateCompanyData(
                denomination: $denomination,
                vatNumber: $record['vat_number'] ?? null,
                address: $address,
            ),
        );

        $company->old_id = $externalId;
        $company->save();

        return MigrationRowOutcome::created($warnings);
    }

    /**
     * Build the company's primary address from the external geo NAMES, or
     * null when no `street` was supplied (no address attempted at all).
     *
     * @param  array<string, mixed>  $record
     * @return array{0: ?CreateAddress, 1: array<int, string>}
     */
    private function buildAddress(array $record): array
    {
        $line1 = trim((string) ($record['street'] ?? ''));

        if ($line1 === '') {
            return [null, []];
        }

        $geo = $this->geoResolver->resolve(
            $record['country'] ?? null,
            $record['region'] ?? null,
            $record['province'] ?? null,
            $record['city'] ?? null,
        );

        $address = new CreateAddress(
            line1: $line1,
            postalCode: $record['postal_code'] ?? null,
            cityId: $geo->cityId,
            provinceId: $geo->provinceId,
            stateId: $geo->stateId,
            countryId: $geo->countryId,
            isPrimary: true,
        );

        return [$address, $geo->warnings];
    }
}

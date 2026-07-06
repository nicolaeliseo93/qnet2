<?php

use App\Enums\ImportStatus;
use App\Models\City;
use App\Models\Company;
use App\Models\Country;
use App\Models\ImportRun;
use App\Models\Province;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/**
 * A real geo chain (country -> state/region -> province -> city), mirroring
 * CompanyCrudTest::companyGeoChain().
 *
 * @return array{country: Country, state: State, province: Province, city: City}
 */
function companiesImportGeoChain(): array
{
    // Reference dataset spelling is ENGLISH (world.sql); the CSV below carries
    // the Italian names + province plate code the resolver localizes onto it.
    $country = Country::factory()->create(['name' => 'Italy']);
    $state = State::factory()->create(['name' => 'Lombardy', 'country_id' => $country->id]);
    $province = Province::factory()->create(['name' => 'Milan', 'state_id' => $state->id, 'country_id' => $country->id]);
    $city = City::factory()->create(['name' => 'Milan', 'province_id' => $province->id, 'state_id' => $state->id, 'country_id' => $country->id]);

    return compact('country', 'state', 'province', 'city');
}

/**
 * CompaniesImportDefinition, end-to-end through the real registered
 * `companies` domain (config/imports.php) — spec 0012 AC-013.
 */
it('AC-013: dry-run + commit creates a Company with its primary address, geo names resolved; motivated scarti', function () {
    Storage::fake('local');
    companiesImportGeoChain();
    Company::factory()->create(['denomination' => 'Existing Srl']);

    $header = 'denomination,vat_number,country,region,province,city,street,postal_code';
    $csv = $header."\n"
        .'Acme Srl,IT12345678901,Italia,Lombardia,MI,Milano,Via Roma 1,20100'."\n" // valid (IT names + MI plate code, localized)
        .',IT000,,,,,,'."\n" // invalid: denomination missing
        .'Beta Srl,,Nonexistentland,,,,,'."\n" // invalid: geo not found
        .'Existing Srl,,,,,,,'."\n"; // invalid: denomination duplicates existing DB row
    Storage::disk('local')->put('imports/companies.csv', $csv);

    $actor = User::factory()->create();
    $run = ImportRun::factory()->create([
        'user_id' => $actor->id,
        'resource' => 'companies',
        'status' => ImportStatus::Validating,
        'stored_path' => 'imports/companies.csv',
    ]);

    runValidateImportJob($run);

    $validated = $run->fresh();
    expect($validated->status)->toBe(ImportStatus::AwaitingConfirmation)
        ->and($validated->total_rows)->toBe(4)
        ->and($validated->valid_rows)->toBe(1)
        ->and($validated->invalid_rows)->toBe(3);

    $invalidReasons = collect($validated->preview['invalid_sample'])->pluck('errors')->flatten()->implode(' ');
    expect($invalidReasons)->toContain('denomination is required')
        ->and($invalidReasons)->toContain('Nonexistentland')
        ->and(Company::query()->count())->toBe(1); // dry-run created nothing

    $run->update(['status' => ImportStatus::Processing]);
    runProcessImportJob($run);

    $processed = $run->fresh();
    expect($processed->status)->toBe(ImportStatus::Completed)
        ->and($processed->imported_rows)->toBe(1);

    $company = Company::query()->where('denomination', 'Acme Srl')->firstOrFail();
    expect($company->vat_number)->toBe('IT12345678901');

    $address = $company->addresses()->firstOrFail();
    expect($address->line1)->toBe('Via Roma 1')
        ->and($address->postal_code)->toBe('20100')
        ->and($address->is_primary)->toBeTrue()
        ->and($address->city->name)->toBe('Milan')
        ->and($address->province->name)->toBe('Milan')
        ->and($address->state->name)->toBe('Lombardy')
        ->and($address->country->name)->toBe('Italy');
});

it('AC-013: a company with no address columns filled is created with no address', function () {
    Storage::fake('local');
    Storage::disk('local')->put(
        'imports/companies.csv',
        "denomination,vat_number,country,region,province,city,street,postal_code\nNo Address Srl,,,,,,,\n"
    );

    $actor = User::factory()->create();
    $run = ImportRun::factory()->create([
        'user_id' => $actor->id,
        'resource' => 'companies',
        'status' => ImportStatus::Processing,
        'stored_path' => 'imports/companies.csv',
    ]);

    runProcessImportJob($run);

    expect($run->fresh()->imported_rows)->toBe(1);
    $company = Company::query()->where('denomination', 'No Address Srl')->firstOrFail();
    expect($company->addresses()->count())->toBe(0);
});

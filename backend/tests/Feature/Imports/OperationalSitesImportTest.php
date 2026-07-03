<?php

use App\Enums\ImportStatus;
use App\Models\City;
use App\Models\Country;
use App\Models\ImportRun;
use App\Models\OperationalSite;
use App\Models\Province;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

/**
 * @return array{country: Country, state: State, province: Province, city: City}
 */
function operationalSitesImportGeoChain(): array
{
    $country = Country::factory()->create(['name' => 'Italia']);
    $state = State::factory()->create(['name' => 'Lombardia', 'country_id' => $country->id]);
    $province = Province::factory()->create(['name' => 'Milano', 'state_id' => $state->id, 'country_id' => $country->id]);
    $city = City::factory()->create(['name' => 'Milano', 'province_id' => $province->id, 'state_id' => $state->id, 'country_id' => $country->id]);

    return compact('country', 'state', 'province', 'city');
}

/**
 * OperationalSitesImportDefinition, end-to-end through the real registered
 * `operational-sites` domain (config/imports.php) — spec 0012 AC-014.
 */
it('AC-014: dry-run + commit creates a site with its address; missing city/street/geo scarti; intra-file dedup on city+street', function () {
    Storage::fake('local');
    operationalSitesImportGeoChain();

    $header = 'country,region,province,city,street,postal_code';
    $csv = $header."\n"
        .'Italia,Lombardia,Milano,Milano,Via Roma 1,20100'."\n" // valid
        .',,,,'.'Via Torino 2'.",\n" // invalid: city missing
        .'Italia,Lombardia,Milano,Milano,,20100'."\n" // invalid: street missing
        .'Nonexistentland,,,Milano,Via Verdi 3,'."\n" // invalid: geo not found
        .'Italia,Lombardia,Milano,Milano,Via Roma 1,20100'."\n"; // invalid: intra-file duplicate of row 1 (city+street)
    Storage::disk('local')->put('imports/operational-sites.csv', $csv);

    $actor = User::factory()->create();
    $run = ImportRun::factory()->create([
        'user_id' => $actor->id,
        'resource' => 'operational-sites',
        'status' => ImportStatus::Validating,
        'stored_path' => 'imports/operational-sites.csv',
    ]);

    runValidateImportJob($run);

    $validated = $run->fresh();
    expect($validated->status)->toBe(ImportStatus::AwaitingConfirmation)
        ->and($validated->total_rows)->toBe(5)
        ->and($validated->valid_rows)->toBe(1)
        ->and($validated->invalid_rows)->toBe(4)
        ->and(OperationalSite::query()->count())->toBe(0); // dry-run created nothing

    $invalidReasons = collect($validated->preview['invalid_sample'])->pluck('errors')->flatten()->implode(' ');
    expect($invalidReasons)->toContain('city is required')
        ->and($invalidReasons)->toContain('street is required')
        ->and($invalidReasons)->toContain('Nonexistentland')
        ->and($invalidReasons)->toContain('Duplicate row within the file');

    $run->update(['status' => ImportStatus::Processing]);
    runProcessImportJob($run);

    $processed = $run->fresh();
    expect($processed->status)->toBe(ImportStatus::Completed)
        ->and($processed->imported_rows)->toBe(1);

    expect(OperationalSite::query()->count())->toBe(1);
    $site = OperationalSite::query()->first();
    $address = $site->addresses()->firstOrFail();
    expect($address->line1)->toBe('Via Roma 1')
        ->and($address->postal_code)->toBe('20100')
        ->and($address->is_primary)->toBeTrue()
        ->and($address->city->name)->toBe('Milano');
});

it('AC-014: two sites at the SAME city+street across separate runs are both allowed (no DB natural key)', function () {
    Storage::fake('local');
    operationalSitesImportGeoChain();

    $csv = "country,region,province,city,street,postal_code\nItalia,Lombardia,Milano,Milano,Via Roma 1,20100\n";
    Storage::disk('local')->put('imports/operational-sites-2.csv', $csv);

    // A site already exists at this exact city+street (a prior import, or manual creation).
    OperationalSite::factory()->withAddress()->create();

    $actor = User::factory()->create();
    $run = ImportRun::factory()->create([
        'user_id' => $actor->id,
        'resource' => 'operational-sites',
        'status' => ImportStatus::Processing,
        'stored_path' => 'imports/operational-sites-2.csv',
    ]);

    runProcessImportJob($run);

    // existsInDatabase() always returns false for this resource: the row is
    // NOT rejected as a duplicate merely because other sites already exist.
    expect($run->fresh()->imported_rows)->toBe(1);
});

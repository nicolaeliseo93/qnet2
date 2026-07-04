<?php

use App\Enums\MigrationStatus;
use App\Jobs\RunMigrationJob;
use App\Migrations\MigrationRegistry;
use App\Models\Company;
use App\Models\Country;
use App\Models\MigrationRun;
use App\Models\Role;
use App\Models\State;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

if (! function_exists('fakeMigrationsBaseUrl')) {
    function fakeMigrationsBaseUrl(): string
    {
        return 'https://external-crm.test';
    }
}

if (! function_exists('seedMigrationsConfig')) {
    function seedMigrationsConfig(): void
    {
        config([
            'migrations.base_url' => fakeMigrationsBaseUrl(),
            'migrations.token' => null,
            'migrations.timeout' => 5,
            'migrations.retry_times' => 1,
            'migrations.retry_sleep_ms' => 1,
            'migrations.import_batch_size' => 100,
        ]);
    }
}

if (! function_exists('migrationsSuperAdminActor')) {
    function migrationsSuperAdminActor(): User
    {
        Role::query()->firstOrCreate(['name' => 'super-admin']);

        $actor = User::factory()->create();
        $actor->assignRole('super-admin');

        return $actor;
    }
}

if (! function_exists('runMigrationJobFor')) {
    function runMigrationJobFor(MigrationRun $run): void
    {
        (new RunMigrationJob($run->id))->handle(app(MigrationRegistry::class));
    }
}

// ---------------------------------------------------------------------------
// CompaniesSource — create + address (geo resolved by name)
// ---------------------------------------------------------------------------

it('creates a company with its address, resolving geo names to ids', function () {
    seedMigrationsConfig();
    $country = Country::factory()->create(['name' => 'Italy']);
    $state = State::factory()->for($country)->create(['name' => 'Lazio']);

    Http::fake([
        fakeMigrationsBaseUrl().'/companies*' => Http::response([
            'items' => [[
                'id' => 1,
                'denomination' => 'Acme Srl',
                'vat_number' => 'IT12345678901',
                'country' => 'Italy',
                'region' => 'Lazio',
                'city' => 'Rome',
                'street' => 'Via Roma 1',
                'postal_code' => '00100',
            ]],
            'pagination' => ['total' => 1],
        ]),
    ]);

    $actor = migrationsSuperAdminActor();
    $run = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'companies']);

    runMigrationJobFor($run);

    $company = Company::query()->where('denomination', 'Acme Srl')->first();

    expect($company)->not->toBeNull()
        ->and($company->old_id)->toBe(1)
        ->and($company->vat_number)->toBe('IT12345678901');

    $address = $company->addresses()->first();
    expect($address)->not->toBeNull()
        ->and($address->line1)->toBe('Via Roma 1')
        ->and($address->country_id)->toBe($country->id)
        ->and($address->state_id)->toBe($state->id)
        // 'Rome' has no matching City fixture: unresolved, non-fatal.
        ->and($address->city_id)->toBeNull();

    $fresh = $run->fresh();
    expect($fresh->status)->toBe(MigrationStatus::Completed)
        ->and($fresh->created_rows)->toBe(1)
        ->and($fresh->report)->not->toBeNull()
        ->and($fresh->report[0]['level'])->toBe('warning')
        ->and($fresh->report[0]['message'])->toContain('Rome');
});

it('creates a company with no address at all when street is absent', function () {
    seedMigrationsConfig();
    Http::fake([
        fakeMigrationsBaseUrl().'/companies*' => Http::response([
            'items' => [['id' => 2, 'denomination' => 'No Address Ltd']],
            'pagination' => ['total' => 1],
        ]),
    ]);

    $actor = migrationsSuperAdminActor();
    $run = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'companies']);

    runMigrationJobFor($run);

    $company = Company::query()->where('denomination', 'No Address Ltd')->first();

    expect($company)->not->toBeNull()
        ->and($company->addresses()->count())->toBe(0);

    expect($run->fresh()->created_rows)->toBe(1);
});

it('isolates a failed row (missing denomination) without blocking the valid one', function () {
    seedMigrationsConfig();
    Http::fake([
        fakeMigrationsBaseUrl().'/companies*' => Http::response([
            'items' => [
                ['id' => 3, 'denomination' => ''],
                ['id' => 4, 'denomination' => 'Valid Company'],
            ],
            'pagination' => ['total' => 2],
        ]),
    ]);

    $actor = migrationsSuperAdminActor();
    $run = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'companies']);

    runMigrationJobFor($run);

    expect(Company::query()->where('denomination', 'Valid Company')->exists())->toBeTrue();

    $fresh = $run->fresh();
    expect($fresh->status)->toBe(MigrationStatus::Completed)
        ->and($fresh->created_rows)->toBe(1)
        ->and($fresh->failed_rows)->toBe(1)
        ->and(collect($fresh->report)->firstWhere('level', 'error'))->not->toBeNull();
});

it('re-importing the same companies is idempotent (skip, no duplicate)', function () {
    seedMigrationsConfig();
    Http::fake([
        fakeMigrationsBaseUrl().'/companies*' => Http::response([
            'items' => [['id' => 5, 'denomination' => 'Already migrated Srl']],
            'pagination' => ['total' => 1],
        ]),
    ]);

    $actor = migrationsSuperAdminActor();
    $firstRun = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'companies']);
    runMigrationJobFor($firstRun);

    $secondRun = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'companies']);
    runMigrationJobFor($secondRun);

    expect(Company::query()->where('denomination', 'Already migrated Srl')->count())->toBe(1);

    $fresh = $secondRun->fresh();
    expect($fresh->skipped_rows)->toBe(1)
        ->and($fresh->created_rows)->toBe(0);
});

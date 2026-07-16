<?php

use App\Models\City;
use App\Models\Company;
use App\Models\CompanySite;
use App\Models\User;
use Database\Seeders\DemoCompanySiteSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Seeds the geo + company + user prerequisites DemoCompanySiteSeeder needs,
 * mirroring the dependency order declared in DemoDataSeeder.
 */
function seedCompanySiteDependencies(): void
{
    City::factory()->count(10)->create();
    Company::factory()->count(4)->create();
    User::factory()->count(6)->create();
}

it('seeds sites and assigns responsible users from the seeded user pool', function (): void {
    seedCompanySiteDependencies();

    test()->seed(DemoCompanySiteSeeder::class);

    expect(CompanySite::count())->toBeGreaterThan(0);

    // At least one site ends up with at least one responsible user set (each of
    // the four FKs is present ~70% of the time, so across several sites the
    // table is reliably non-empty).
    $withResponsible = CompanySite::query()
        ->where(fn ($query) => $query
            ->whereNotNull('responsible_rda_id')
            ->orWhereNotNull('responsible_tickets_id')
            ->orWhereNotNull('responsible_validation_contracts_id')
            ->orWhereNotNull('responsible_validation_contracts_two_id'))
        ->count();

    expect($withResponsible)->toBeGreaterThanOrEqual(1);

    // Every assigned responsible points at a real seeded user.
    $userIds = User::query()->pluck('id')->all();

    CompanySite::query()->get()->each(function (CompanySite $site) use ($userIds): void {
        foreach (['responsible_rda_id', 'responsible_tickets_id', 'responsible_validation_contracts_id', 'responsible_validation_contracts_two_id'] as $column) {
            if ($site->{$column} !== null) {
                expect($site->{$column})->toBeIn($userIds);
            }
        }
    });
});

it('leaves the responsible FKs null when no users are seeded', function (): void {
    City::factory()->count(10)->create();
    Company::factory()->count(2)->create();

    test()->seed(DemoCompanySiteSeeder::class);

    expect(CompanySite::count())->toBeGreaterThan(0);
    expect(CompanySite::whereNotNull('responsible_rda_id')->count())->toBe(0);
    expect(CompanySite::whereNotNull('responsible_tickets_id')->count())->toBe(0);
});

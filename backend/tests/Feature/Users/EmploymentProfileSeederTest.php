<?php

use App\Models\BusinessFunction;
use App\Models\Company;
use App\Models\EmploymentProfile;
use App\Models\OperationalSite;
use App\Models\User;
use Database\Seeders\DemoEmploymentProfileSeeder;
use Database\Seeders\DemoRolesSeeder;
use Database\Seeders\DemoUsersSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Feature coverage for AC-013 (spec 0015): EmploymentProfileFactory states +
 * the seeder's manager/subordinate hierarchy.
 */

// ---------------------------------------------------------------------------
// Seeder
// ---------------------------------------------------------------------------

it('seeds at least 2 managers and every other seeded user reports to one of them (no self-reference)', function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(DemoRolesSeeder::class);
    $this->seed(DemoUsersSeeder::class);

    $this->seed(DemoEmploymentProfileSeeder::class);

    $managers = EmploymentProfile::where('is_manager', true)->get();
    expect($managers->count())->toBeGreaterThanOrEqual(2);

    foreach ($managers as $manager) {
        expect($manager->reports_to_id)->toBeNull();
    }

    $managerUserIds = $managers->pluck('user_id')->all();

    $subordinates = EmploymentProfile::where('is_manager', false)->get();
    expect($subordinates->count())->toBeGreaterThanOrEqual(1);

    foreach ($subordinates as $subordinate) {
        expect($subordinate->reports_to_id)->not->toBeNull()
            ->and($subordinate->reports_to_id)->toBeIn($managerUserIds)
            ->and($subordinate->reports_to_id)->not->toBe($subordinate->user_id);
    }
});

it('fills the contractual FKs (function/company/operational site) from the seeded lookups', function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(DemoRolesSeeder::class);
    $this->seed(DemoUsersSeeder::class);
    BusinessFunction::factory()->count(4)->create();
    Company::factory()->count(4)->create();
    OperationalSite::factory()->count(4)->create();

    $this->seed(DemoEmploymentProfileSeeder::class);

    // Each FK is present ~75% of the time; across every seeded profile the
    // three columns are reliably non-empty.
    expect(EmploymentProfile::whereNotNull('business_function_id')->count())->toBeGreaterThanOrEqual(1);
    expect(EmploymentProfile::whereNotNull('company_id')->count())->toBeGreaterThanOrEqual(1);
    expect(EmploymentProfile::whereNotNull('operational_site_id')->count())->toBeGreaterThanOrEqual(1);

    // Every assigned FK points at a real seeded row.
    $businessFunctionIds = BusinessFunction::pluck('id')->all();
    EmploymentProfile::whereNotNull('business_function_id')->pluck('business_function_id')
        ->each(fn (int $id) => expect($id)->toBeIn($businessFunctionIds));
});

it('leaves the contractual FKs null when no lookups are seeded', function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(DemoRolesSeeder::class);
    $this->seed(DemoUsersSeeder::class);

    $this->seed(DemoEmploymentProfileSeeder::class);

    expect(EmploymentProfile::whereNotNull('business_function_id')->count())->toBe(0);
    expect(EmploymentProfile::whereNotNull('company_id')->count())->toBe(0);
    expect(EmploymentProfile::whereNotNull('operational_site_id')->count())->toBe(0);
});

it('is idempotent — re-running does not duplicate employment rows', function () {
    $this->seed(RolePermissionSeeder::class);
    $this->seed(DemoRolesSeeder::class);
    $this->seed(DemoUsersSeeder::class);
    $this->seed(DemoEmploymentProfileSeeder::class);
    $countBefore = EmploymentProfile::count();

    $this->seed(DemoEmploymentProfileSeeder::class);

    expect(EmploymentProfile::count())->toBe($countBefore);
});

// ---------------------------------------------------------------------------
// Factory states
// ---------------------------------------------------------------------------

it('EmploymentProfileFactory::manager() forces is_manager true and reports_to null', function () {
    $employment = EmploymentProfile::factory()->manager()->create();

    expect($employment->is_manager)->toBeTrue()
        ->and($employment->reports_to_id)->toBeNull();
});

it('EmploymentProfileFactory::reportsTo() points to the given manager', function () {
    $manager = User::factory()->create();

    $employment = EmploymentProfile::factory()->reportsTo($manager)->create();

    expect($employment->is_manager)->toBeFalse()
        ->and($employment->reports_to_id)->toBe($manager->id);
});

it('UserFactory::withEmployment()/manager()/reportsTo() attach an employment profile', function () {
    $manager = User::factory()->manager()->create();
    expect($manager->employment)->not->toBeNull()
        ->and($manager->employment->is_manager)->toBeTrue();

    $subordinate = User::factory()->reportsTo($manager)->create();
    expect($subordinate->employment->reports_to_id)->toBe($manager->id);

    $plain = User::factory()->withEmployment()->create();
    expect($plain->employment)->not->toBeNull();
});

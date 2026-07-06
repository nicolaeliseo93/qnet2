<?php

use App\Models\EmploymentProfile;
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

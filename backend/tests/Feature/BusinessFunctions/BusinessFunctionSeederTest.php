<?php

use App\Models\BusinessFunction;
use App\Models\User;
use Database\Seeders\DemoBusinessFunctionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// Seeder
// ---------------------------------------------------------------------------

it('seeds the curated business functions with managers and members', function () {
    User::factory()->count(12)->create();

    $this->seed(DemoBusinessFunctionSeeder::class);

    expect(BusinessFunction::count())->toBe(15);

    $functions = BusinessFunction::with('users')->get();

    // Every seeded function has a responsible manager and at least 2 members.
    $functions->each(function (BusinessFunction $function): void {
        expect($function->manager_id)->not->toBeNull();
        expect($function->users->count())->toBeGreaterThanOrEqual(2);
        // Mutually exclusive type invariant (spec 0010): never both true.
        expect($function->is_business_unit && $function->is_business_service)->toBeFalse();
    });
});

it('is idempotent — re-running does not duplicate functions or memberships', function () {
    User::factory()->count(12)->create();
    error_log('DEBUG userIds before 1st seed: '.User::query()->pluck('id')->implode(','), 3, '/tmp/pao_debug.log');

    $this->seed(DemoBusinessFunctionSeeder::class);
    $membershipCount = BusinessFunction::first()->users()->count();
    error_log('DEBUG after 1st seed: bfCount='.BusinessFunction::count().' membership='.$membershipCount.' userIds='.User::query()->pluck('id')->implode(','), 3, '/tmp/pao_debug.log');

    $this->seed(DemoBusinessFunctionSeeder::class);
    error_log('DEBUG after 2nd seed: bfCount='.BusinessFunction::count().' membership='.BusinessFunction::first()->users()->count().' userIds='.User::query()->pluck('id')->implode(','), 3, '/tmp/pao_debug.log');

    expect(BusinessFunction::count())->toBe(15);
    expect(BusinessFunction::first()->users()->count())->toBe($membershipCount);
});

it('seeds functions even with no users to associate', function () {
    $this->seed(DemoBusinessFunctionSeeder::class);

    expect(BusinessFunction::count())->toBe(15);
    expect(BusinessFunction::whereNotNull('manager_id')->count())->toBe(0);
});

// ---------------------------------------------------------------------------
// Factory states
// ---------------------------------------------------------------------------

it('exposes exclusive type states', function () {
    expect(BusinessFunction::factory()->businessUnit()->create())
        ->is_business_unit->toBeTrue()
        ->is_business_service->toBeFalse();

    expect(BusinessFunction::factory()->businessService()->create())
        ->is_business_unit->toBeFalse()
        ->is_business_service->toBeTrue();
});

it('assigns a manager via the withManager state', function () {
    $manager = User::factory()->create();

    expect(BusinessFunction::factory()->withManager($manager)->create()->manager_id)
        ->toBe($manager->id);

    // Without an explicit user, a manager is still created.
    expect(BusinessFunction::factory()->withManager()->create()->manager_id)->not->toBeNull();
});

it('attaches associated users via the withUsers state', function () {
    $users = User::factory()->count(4)->create();

    $function = BusinessFunction::factory()->withUsers(3, $users)->create();

    expect($function->users)->toHaveCount(3);
});
